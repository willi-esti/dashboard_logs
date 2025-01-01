#!/bin/bash

set -e

# Function to display usage
usage() {
    echo "Usage: $0 [--install] [--enable-http] [--enable-ssl] [--firewall] [--add-sudo-rules] [--remove-sudo-rules] [--uninstall]"
    echo "  --install           Install the server dashboard, SELinux configuration if Enforcing, and logrotate configuration"
    echo "  --enable-http       Enable HTTP for the server dashboard"
    echo "  --enable-ssl        Enable SSL for the server dashboard"
    echo "  --firewall          Configure firewall, ufw for Debian and firewalld for Red Hat, ports 80, 443"
    echo "  --add-sudo-rules    Add sudo rules for services"
    echo "  --remove-sudo-rules Remove sudo rules for services"
    echo "  --uninstall         Uninstall the server dashboard, remove SELinux configuration, and logrotate configuration"
    echo ""
    echo "Example: $0 --install --enable-http --enable-ssl --firewall --add-sudo-rules"
    exit 1
}

# Function to verify the .env variables
verify_env() {
    if [ ! -d public ] || [ ! -f composer.json ]; then
        error "public folder or composer.json file is missing."
        error "Please make sure you are running the script in the correct directory."
        exit 1
    fi

    if [ -z "$APP_DIR" ] || [ -z "$SERVICES" ] || [ -z "$LOG_DIRS" ]; then
        error "APP_DIR, SERVICES, and LOG_DIRS are required in the .env file."
        exit 1
    fi
    IFS=',' read -r -a services <<< "$SERVICES"
    for service in "${services[@]}"; do
        if [[ ! "$service" =~ ^[a-zA-Z0-9\._-]+$ ]]; then
            error "Invalid service name: $service"
            exit 1
        fi
    done
    IFS=',' read -r -a log_dirs <<< "$LOG_DIRS"
    for log_dir in "${log_dirs[@]}"; do
        if [ ! -d "$log_dir" ]; then
            warning "Log directory $log_dir does not exist."
        fi
        if [[ "$log_dir" =~ "^.*\/$" ]]; then
            error "Log directory $log_dir should end with a slash."
            exit 1
        fi
    done
}

# Function to detect the operating system
detect_os() {
    if [ -f /etc/redhat-release ]; then
        OS="redhat"
        APACHE_SERVICE="httpd"
        APACHE_SSL_DIR="httpd/ssl"
        WEB_USER="apache"
    elif [ -f /etc/debian_version ]; then
        OS="debian"
        APACHE_SERVICE="apache2"
        APACHE_SSL_DIR="apache2/ssl"
        WEB_USER="www-data"
    else
        error "Unsupported operating system."
        exit 1
    fi
}

# Function to display information messages
info() {
    echo -e "\e[32mINFO: $1\e[0m"
}

# Function to display warning messages
warning() {
    echo -e "\e[33mWARNING: $1\e[0m"
    while true; do
        read -p "Do you want to continue anyway? (y/n): " choice
        case "$choice" in 
            y|Y ) echo "Continuing..."; break;;
            n|N ) echo "Exiting..."; exit 1;;
            * ) echo "Invalid choice. Please enter y or n.";;
        esac
    done
}

# Function to display error messages
error() {
    echo -e "\e[31mERROR: $1\e[0m"
}

# Function to install packages
install_packages() {
    if [ "$OS" = "debian" ]; then
        apt-get update
        apt-get install -y "$@"
    elif [ "$OS" = "redhat" ]; then
        yum install -y "$@"
    fi
}

# Function to restart services
restart_service() {
    systemctl restart "$1"
}

# Function to enable services
enable_service() {
    systemctl enable "$1"
}

# Function to start services
start_service() {
    systemctl start "$1"
}

# Function to stop services
stop_service() {
    systemctl stop "$1"
}

# Function to disable services
disable_service() {
    systemctl disable "$1"
}

# Function to enable Apache modules
enable_apache_module() {
    if [ "$OS" = "debian" ]; then
        a2enmod "$1"
    elif [ "$OS" = "redhat" ]; then
        sed -i "s/#LoadModule ${1}_module/LoadModule ${1}_module/" /etc/httpd/conf.modules.d/00-base.conf
    fi
}

# Function to enable Apache sites
enable_apache_site() {
    if [ "$OS" = "debian" ]; then
        a2ensite "$1"
    fi
}

# Function to disable Apache sites
disable_apache_site() {
    if [ "$OS" = "debian" ]; then
        a2dissite "$1"
    fi
}

# Function to configure SELinux
configure_selinux() {
    if command -v getenforce &> /dev/null; then
        if [ "$(getenforce)" != "Disabled" ]; then
            # Install policycoreutils if not already installed semanage
            install_packages policycoreutils policycoreutils-python-utils

            info "Configuring SELinux policies..."

            # Allow Apache to connect to the network
            setsebool -P httpd_can_network_connect 1

            # Allow Apache to connect to the WebSocket server
            setsebool -P httpd_can_network_relay 1

            # Allow Apache to read and write to the app directory
            semanage fcontext -a -t httpd_sys_rw_content_t "${APP_DIR}(/.*)?"
            restorecon -Rv ${APP_DIR}

            # Allow Apache to execute systemctl status
            echo "
module httpd_systemctl 1.0;

require {
    type httpd_sys_content_t;
    type httpd_t;
    type crond_unit_file_t;
    type httpd_unit_file_t;
    type sshd_unit_file_t;
    type syslogd_var_run_t;
    type systemd_unit_file_t;
    type init_t;
    type shadow_t;
    type pam_var_run_t;
    class service status;
    class capability sys_resource;
    class dir { add_name write read };
    class file { getattr open read create write lock };
    class file append;
}

#============= httpd_t ==============
allow httpd_t crond_unit_file_t:service status;

#!!!! This avc is allowed in the current policy
allow httpd_t httpd_unit_file_t:service status;
allow httpd_t init_t:service status;
allow httpd_t sshd_unit_file_t:service status;
allow httpd_t syslogd_var_run_t:dir read;

#!!!! This avc is allowed in the current policy
allow httpd_t systemd_unit_file_t:service status;

#!!!! This avc can be allowed using the boolean 'httpd_unified'
allow httpd_t httpd_sys_content_t:file append;

#!!!! This avc can be allowed using sudo
allow httpd_t pam_var_run_t:dir { add_name write read };
allow httpd_t pam_var_run_t:file { create read getattr open write lock };
allow httpd_t self:capability sys_resource;
allow httpd_t shadow_t:file { open read };
allow httpd_t shadow_t:file getattr;

#!!!! This avc can be allowed reading files
allow httpd_t shadow_t:file read;
" > /tmp/httpd_systemctl.te

            checkmodule -M -m -o /tmp/httpd_systemctl.mod /tmp/httpd_systemctl.te;semodule_package -m /tmp/httpd_systemctl.mod -o /tmp/httpd_systemctl.pp;semodule -i /tmp/httpd_systemctl.pp

            # Allow Apache to read and write to the log directories
            IFS=',' read -r -a log_dirs <<< "$LOG_DIRS"
            for log_dir in "${log_dirs[@]}"; do
                semanage fcontext -a -t httpd_sys_rw_content_t "${log_dir}"
                semanage fcontext -a -t httpd_sys_rw_content_t "${log_dir}(/.*)?"
                restorecon -Rv ${log_dir}
            done

            # crutial cause if some workers are still running they won't have access to the new context
            restart_service php-fpm
            
            info "SELinux configuration complete."
        else
            info "SELinux is disabled."
        fi
    else
        info "SELinux is not installed."
    fi
}

# Function to ensure Apache or www-data user can access log directories
configure_log_dirs() {
    LOG_GROUP="loggroup"
    groupadd -f ${LOG_GROUP}
    usermod -aG ${LOG_GROUP} ${WEB_USER}

    IFS=',' read -r -a log_dirs <<< "$LOG_DIRS"
    for log_dir in "${log_dirs[@]}"; do
        chgrp -R ${LOG_GROUP} "$log_dir"
        chmod -R g+rwX "$log_dir"
    done
}

# Function to configure logrotate
configure_logrotate() {
    info "Configuring logrotate..."

    # Install logrotate if not already installed
    install_packages logrotate

    LOG_DIR=${LOG_DIR:-${APP_DIR}/logs}

    bash -c "cat <<EOF > /etc/logrotate.d/server-dashboard
$LOG_DIR/*.log {
    size 50M
    rotate 5
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
    create 0640 ${WEB_USER} ${WEB_USER}
}
EOF"

    info "Logrotate configuration complete."

    # Reload logrotate configuration if needed
    systemctl restart logrotate.timer
}

# Function to configure firewall
configure_firewall() {
    info "Configuring firewall..."

    if [ "$OS" = "debian" ]; then
        install_packages ufw
        ufw allow 80
        if [ "$ENABLE_SSL" = true ]; then
            ufw allow 443
        fi
        ufw --force enable
    elif [ "$OS" = "redhat" ]; then
        install_packages firewalld
        systemctl start firewalld
        systemctl enable firewalld
        firewall-cmd --zone=public --add-service=http --permanent
        if [ "$ENABLE_SSL" = true ]; then
            firewall-cmd --zone=public --add-service=https --permanent
        fi
        firewall-cmd --reload
    fi

    info "Firewall configuration complete."
}

# Check if the script is run as root
if [ "$EUID" -ne 0 ]; then
    warning "This script must be run as root."
    exit 1
fi

# Check if no arguments are provided
if [ "$#" -eq 0 ]; then
    usage
fi

# Check for .env file
if [ ! -f .env ]; then
    error ".env file is missing."
    info "Please create it by copying the .env.example file:"
    info "cp .env.example .env"
    exit 1
fi

# Load environment variables
source .env

# Verify the .env variables
info "Verifying environment variables..."
verify_env

# Detect the operating system
info "Detecting operating system..."
detect_os

# Parse arguments
ENABLE_SSL=false
ENABLE_HTTP=false
UNINSTALL=false
INSTALL=false
ADD_SUDO_RULES=false
REMOVE_SUDO_RULES=false
FIREWALL=false
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --install) INSTALL=true ;;
        --enable-http) ENABLE_HTTP=true ;;
        --enable-ssl) ENABLE_SSL=true ;;
        --firewall) FIREWALL=true ;;
        --add-sudo-rules) ADD_SUDO_RULES=true ;;
        --remove-sudo-rules) REMOVE_SUDO_RULES=true ;;
        --uninstall) UNINSTALL=true ;;
        *) usage ;;
    esac
    shift
done

if [ "$UNINSTALL" = true ]; then
    info "Uninstalling server dashboard..."

    info "Stopping Apache and websocket server..."
    stop_service ${APACHE_SERVICE}
    stop_service websocket-server

    info "Disabling websocket server..."
    disable_service websocket-server

    info "Removing websocket server service file..."
    rm -f /etc/systemd/system/websocket-server.service

    info "Removing server dashboard files..."
    rm -rf ${APP_DIR}

    info "Removing Apache configuration for SSL if exists..."
    if [ "$OS" = "debian" ]; then
        disable_apache_site server-dashboard-ssl
        rm -f /etc/apache2/sites-available/server-dashboard-ssl.conf
        disable_apache_site server-dashboard
        rm -f /etc/apache2/sites-available/server-dashboard.conf
    elif [ "$OS" = "redhat" ]; then
        rm -f /etc/httpd/conf.d/server-dashboard.conf
        rm -f /etc/httpd/conf.d/server-dashboard-ssl.conf
    fi
    rm -f /etc/${APACHE_SSL_DIR}/apache.crt /etc/${APACHE_SSL_DIR}/apache.key

    info "Removing sudo rules for services..."
    rm -f /etc/sudoers.d/${WEB_USER}-restart

    info "Restarting Apache to apply changes..."
    restart_service ${APACHE_SERVICE}

    info "Removing firewall configuration..."
    if [ "$OS" = "debian" ]; then
        ufw --force reset
    elif [ "$OS" = "redhat" ]; then
        firewall-cmd --zone=public --remove-service=http --permanent
        firewall-cmd --zone=public --remove-service=https --permanent
        firewall-cmd --reload
    fi

    info "Removing logrotate configuration..."
    rm -f /etc/logrotate.d/server-dashboard

    info "Removing SELinux configuration..."
    if command -v getenforce &> /dev/null; then
        if [ "$(getenforce)" != "Disabled" ]; then
            setsebool -P httpd_can_network_connect 0
            setsebool -P httpd_can_network_relay 0
        fi
    fi

    info "Uninstallation complete."
    exit 0
fi

if [ "$INSTALL" = true ]; then
    info "Installing necessary packages..."
    if [ "$OS" = "debian" ]; then
        install_packages apache2 php libapache2-mod-php
    elif [ "$OS" = "redhat" ]; then
        install_packages httpd php mod_ssl
        systemctl enable ${APACHE_SERVICE}
    fi

    info "Enabling Apache mod_rewrite..."
    enable_apache_module rewrite
    restart_service ${APACHE_SERVICE}

    info "Creating directory for the server dashboard..."
    mkdir -p ${APP_DIR}

    info "Copying files to the server dashboard directory, including hidden files..."
    shopt -s dotglob
    cp -r * ${APP_DIR}
    mkdir -p ${APP_DIR}/logs
    shopt -u dotglob

    info "Setting the correct permissions..."
    chown -R ${WEB_USER}:${WEB_USER} ${APP_DIR}
    chmod -R 755 ${APP_DIR}

    info "Restarting Apache to apply changes..."
    restart_service ${APACHE_SERVICE}
    info "Copying websocket server service file to /etc/systemd/system..."
    cp ${APP_DIR}/system/websocket-server.service /etc/systemd/system/

    info "Replacing placeholders in websocket server service file..."
    sed -i "s|{{APP_DIR}}|${APP_DIR}|g" /etc/systemd/system/websocket-server.service
    sed -i "s|{{WEB_USER}}|${WEB_USER}|g" /etc/systemd/system/websocket-server.service

    info "Installing Composer dependencies..."
    cd ${APP_DIR}
    if [ "$OS" = "debian" ]; then
        install_packages composer
    elif [ "$OS" = "redhat" ]; then
        install_packages php-cli php-zip
        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/sbin/composer
    fi
    su ${WEB_USER} -s /bin/bash -c 'composer install --no-dev --no-interaction'

    if [ "$ENABLE_HTTP" = true ]; then
        info "Setting up HTTP configuration..."
        if [ "$OS" = "debian" ]; then
            CONFIG_PATH="/etc/apache2/sites-available/server-dashboard.conf"
        elif [ "$OS" = "redhat" ]; then
            CONFIG_PATH="/etc/httpd/conf.d/server-dashboard.conf"
        fi
        bash -c "cat <<EOF > $CONFIG_PATH
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName localhost
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ProxyPreserveHost On
    ProxyPass /api/logs/stream ws://localhost:8080/
    ProxyPassReverse /api/logs/stream ws://localhost:8080/

    ErrorLog logs/error.log
    CustomLog logs/access.log combined

    
EOF"
        if [ "$ENABLE_SSL" = true ]; then
            bash -c "cat <<EOF >> $CONFIG_PATH
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
EOF"
        fi
        bash -c "cat <<EOF >> $CONFIG_PATH
</VirtualHost>
EOF"

        enable_apache_site server-dashboard
        systemctl reload ${APACHE_SERVICE}
    fi

    if [ "$ENABLE_SSL" = true ]; then
        info "Enabling SSL and generating self-signed certificates..."
        install_packages openssl
        enable_apache_module ssl
        mkdir -p /etc/${APACHE_SSL_DIR}
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/${APACHE_SSL_DIR}/apache.key -out /etc/${APACHE_SSL_DIR}/apache.crt -subj "/C=US/ST=State/L=City/O=Organization/OU=Department/CN=your_domain.com"
        if [ "$OS" = "debian" ]; then
            CONFIG_PATH="/etc/apache2/sites-available/server-dashboard-ssl.conf"
        elif [ "$OS" = "redhat" ]; then
            CONFIG_PATH="/etc/httpd/conf.d/server-dashboard-ssl.conf"
        fi
        bash -c "cat <<EOF > $CONFIG_PATH
<VirtualHost *:443>
    ServerAdmin webmaster@localhost
    ServerName localhost
    DocumentRoot ${APP_DIR}/public

    SSLEngine on
    SSLCertificateFile /etc/${APACHE_SSL_DIR}/apache.crt
    SSLCertificateKeyFile /etc/${APACHE_SSL_DIR}/apache.key

    <Directory ${APP_DIR}/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ProxyPreserveHost On
    ProxyPass /api/logs/stream ws://localhost:8080/
    ProxyPassReverse /api/logs/stream ws://localhost:8080/

    ErrorLog logs/error.log
    CustomLog logs/access.log combined
</VirtualHost>
EOF"
        enable_apache_module proxy
        enable_apache_module proxy_wstunnel
        enable_apache_site server-dashboard-ssl
        systemctl reload ${APACHE_SERVICE}
    fi
    
    info "Enabling and starting websocket server..."
    enable_service websocket-server
    start_service websocket-server

    if [ "$FIREWALL" = true ]; then
        configure_firewall
    fi

    configure_logrotate

    configure_log_dirs
    
    configure_selinux

    info "Installation complete. Please check your server dashboard at http://your_server_ip/server-dashboard"
    if [ "$ENABLE_SSL" = true ]; then
        info "SSL enabled. Access your server dashboard at https://your_domain.com/server-dashboard"
    fi
fi

if [ "$ADD_SUDO_RULES" = true ]; then
    info "Adding sudo rules for services..."

    # Convert comma-separated strings to arrays
    IFS=',' read -r -a services <<< "$SERVICES"

    # Add restart and stop rules for each filtered service
    for service in "${services[@]}"; do
        RESTART_RULE="${WEB_USER} ALL=(ALL) NOPASSWD: /bin/systemctl restart $service"
        STOP_RULE="${WEB_USER} ALL=(ALL) NOPASSWD: /bin/systemctl stop $service"
        
        if sudo grep -Fxq "$RESTART_RULE" /etc/sudoers.d/${WEB_USER}-restart; then
            info "Restart rule for $service already exists in sudoers file."
        else
            echo "$RESTART_RULE" | sudo tee -a /etc/sudoers.d/${WEB_USER}-restart
            sudo visudo -cf /etc/sudoers.d/${WEB_USER}-restart
            if [ $? -eq 0 ]; then
                info "Restart rule for $service added successfully."
            else
                warning "Failed to add restart rule for $service. Please check the sudoers file syntax."
                sudo rm /etc/sudoers.d/${WEB_USER}-restart
            fi
        fi

        if sudo grep -Fxq "$STOP_RULE" /etc/sudoers.d/${WEB_USER}-restart; then
            info "Stop rule for $service already exists in sudoers file."
        else
            echo "$STOP_RULE" | sudo tee -a /etc/sudoers.d/${WEB_USER}-restart
            sudo visudo -cf /etc/sudoers.d/${WEB_USER}-restart
            if [ $? -eq 0 ]; then
                info "Stop rule for $service added successfully."
            else
                warning "Failed to add stop rule for $service. Please check the sudoers file syntax."
                sudo rm /etc/sudoers.d/${WEB_USER}-restart
            fi
        fi
    done

    info "Sudo rules added successfully."
    exit 0
fi

if [ "$REMOVE_SUDO_RULES" = true ]; then
    info "Removing sudo rules for services..."
    rm -f /etc/sudoers.d/${WEB_USER}-restart
    info "Sudo rules removed successfully."
    exit 0
fi