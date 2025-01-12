#!/bin/bash

set -e

# Function to display usage
usage() {
    echo "Usage: $0 [--install] [--enable-http] [--enable-ssl] [--firewall] [--add-sudo-rules] [--remove-sudo-rules] [--uninstall]"
    echo "  --install           Install the server dashboard, run the websocket-server service and necessary packages, enable Apache modules, configure SELinux policies (if Enforcing), configure logrotate, configure crond (if Selinux is Enforcing), and configure firewall."
    echo "  --enable-http       Enable HTTP for the server dashboard"
    echo "  --enable-ssl        Enable SSL for the server dashboard"
    echo "  --firewall          Configure firewall, ufw for Debian and firewalld for Red Hat, ports 80, 443"
    echo "  --add-sudo-rules    Add sudo rules for services, not used in selinux mode"
    echo "  --remove-sudo-rules Remove sudo rules for services"
    echo "  --selinux           Configure SELinux policies for the server dashboard, a cron job will be used for the restart and stop actions"
    echo "  --uninstall         Uninstall the server dashboard, remove SELinux configuration, logrotate configuration, cron configuration, and firewall configuration"
    echo "  --verify-env        Verify the .env file variables"
    echo ""
    echo "Example : $0 --install --enable-http --enable-ssl --firewall --add-sudo-rules"
    echo "Example (Selinux): $0 --install --enable-http --enable-ssl --firewall --selinux"
    echo "Example (Uninstall): $0 --uninstall"
    exit 1
}

# Function to verify the .env variables
verify_env() {
    if [ ! -d src ] || [ ! -f composer.json ]; then
        error "src folder or composer.json file is missing."
        error "Please make sure you are running the script in the correct directory."
        exit 1
    fi
    if [ -z "$VERSION" ]; then
        error "VERSION is required in the .env file."
        exit 1
    fi
    if [ -z "$SERVER_ID" ]; then
        error "SERVER_ID is required in the .env file."
        exit 1
    fi
    if [ -z "$APP_DIR" ]; then
        error "APP_DIR is required in the .env file."
        exit 1
    fi
    if [ -z "$SERVICES" ]; then
        error "SERVICES is required in the .env file."
        exit 1
    fi
    if [ -z "$LOG_DIRS" ]; then
        error "LOG_DIRS are required in the .env file."
        exit 1
    fi
    if [ -z "$BASE_URL" ]; then
        error "BASE_URL is required in the .env file."
        exit 1
    fi
    if [[ ! "$BASE_URL" =~ ^\/.*$ ]]; then
        error "BASE_URL should start with a slash."
        exit 1
    fi
    if [[ "$BASE_URL" =~ "^.*\/$" ]]; then
        error "BASE_URL should not end with a slash."
        exit 1
    fi
    if [ -z "$TOKEN_API" ]; then
        error "TOKEN_API is required in the .env file."
        exit 1
    fi
    if [ -z "$SELINUX" ]; then
        error "SELINUX is required in the .env file."
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
            warning "Log directory $log_dir does not exist." $1
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
        GROUP_SUDO="wheel"
        CONFIG_PATH="/etc/httpd/conf.d/server-dashboard.conf"
        LOG_DIR="/var/log/httpd"
    elif [ -f /etc/debian_version ]; then
        OS="debian"
        APACHE_SERVICE="apache2"
        APACHE_SSL_DIR="apache2/ssl"
        WEB_USER="www-data"
        GROUP_SUDO="sudo"
        CONFIG_PATH="/etc/apache2/sites-available/server-dashboard.conf"
        LOG_DIR="/var/log/apache2"
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
    if [ "$2" != 1 ]; then
        while true; do
            read -p "Do you want to continue anyway? (y/n): " choice
            case "$choice" in 
                y|Y ) echo "Continuing..."; break;;
                n|N ) echo "Exiting..."; exit 1;;
                * ) echo "Invalid choice. Please enter y or n.";;
            esac
        done
    fi
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
        if [[ "$(getenforce)" != "Disabled" &&   "$MODE" = "selinux" ]]; then
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

            # Allow Apache to read and write to the log directories
            IFS=',' read -r -a log_dirs <<< "$LOG_DIRS"
            for log_dir in "${log_dirs[@]}"; do
                semanage fcontext -a -t httpd_sys_rw_content_t "${log_dir}"
                semanage fcontext -a -t httpd_sys_rw_content_t "${log_dir}(/.*)?"
                restorecon -Rv ${log_dir}
            done

            warning "Please generate some SELinux errors by accessing the server dashboard. All the services will be stopped, just ignore it.\n
            You can always rerun the script with --selinux flag to fix the SELinux policies." "Press y to continue."

            # Allow Apache to execute systemctl status
            info "Generating SELinux policies ..."
            cat /var/log/audit/audit.log  | egrep "apache|denied|status" | audit2allow -m httpd_systemctl > /tmp/httpd_systemctl.te
            checkmodule -M -m -o /tmp/httpd_systemctl.mod /tmp/httpd_systemctl.te;semodule_package -m /tmp/httpd_systemctl.mod -o /tmp/httpd_systemctl.pp;semodule -i /tmp/httpd_systemctl.pp

            # crutial cause if some workers are still running they won't have access to the new context
            restart_service php-fpm httpd

            restart_service websocket-server
            
            info "SELinux configuration complete."
    
            configure_crond
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
        if command -v ufw &> /dev/null; then
            ufw --force enable
            ufw --force allow 80
            if [ "$ENABLE_SSL" = true ]; then
                ufw --force allow 443
            fi
            info "Firewall configuration complete."
        else
            warning "UFW is not installed." 1
        fi
    elif [ "$OS" = "redhat" ]; then
        if command -v firewalld &> /dev/null; then
            systemctl start firewalld
            systemctl enable firewalld
            firewall-cmd --zone=public --add-service=ssh --permanent
            firewall-cmd --zone=public --add-service=http --permanent
            if [ "$ENABLE_SSL" = true ]; then
                firewall-cmd --zone=public --add-service=https --permanent
            fi
            firewall-cmd --reload
            info "Firewall configuration complete."
        else
            warning "Firewalld is not installed." 1
        fi
    fi
}

# Function to configure crond
configure_crond() {
    info "Configuring crond..."

    # Install crond if not already installed
    install_packages cronie

    # search and replace APP_DIR in crontab
    sed -i "s|{{APP_DIR}}|${APP_DIR}|g" ${APP_DIR}/system/server-dashboard-cron

    # Add crontab
    cp ${APP_DIR}/system/server-dashboard-cron /etc/cron.d/server-dashboard
    chmod 644 /etc/cron.d/server-dashboard

    # Start and enable crond
    systemctl enable crond
    systemctl restart crond

    info "Crond configuration complete."
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

# Parse arguments
ENABLE_SSL=false
ENABLE_HTTP=false
UNINSTALL=false
INSTALL=false
ADD_SUDO_RULES=false
REMOVE_SUDO_RULES=false
FIREWALL=false
SELINUX=false
VERIFY_ENV=false
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --install) INSTALL=true ;;
        --enable-http) 
            if [ "$INSTALL" != true ]; then
                error "--enable-http can only be used with --install"
                usage
            fi
            ENABLE_HTTP=true 
            ;;
        --enable-ssl) 
            if [ "$INSTALL" != true ]; then
                error "--enable-ssl can only be used with --install"
                usage
            fi
            ENABLE_SSL=true 
            ;;
        --firewall) FIREWALL=true ;;
        --add-sudo-rules) ADD_SUDO_RULES=true ;;
        --remove-sudo-rules) REMOVE_SUDO_RULES=true ;;
        --selinux) SELINUX_PARAM=true ;;
        --uninstall) UNINSTALL=true ;;
        --verify-env) VERIFY_ENV=true ;;
        *) usage ;;
    esac
    shift
done

# Load environment variables
source .env

if [ "$VERIFY_ENV" = true ]; then
    info "Verifying environment variables..."
    verify_env 1
    info "Environment variables verification complete."
    exit 0
else
    # Verify the .env variables
    info "Verifying environment variables..."
    verify_env
fi

# Declare global variables
OS=""
APACHE_SERVICE=""
APACHE_SSL_DIR=""
WEB_USER=""
GROUP_SUDO=""
CONFIG_PATH=""
LOG_DIR=""

# Detect the operating system
info "Detecting operating system..."
detect_os

if [ "$UNINSTALL" = true ]; then
    # Verify the .env variables
    info "Verifying environment variables..."
    verify_env 1

    info "Uninstalling server dashboard..."

    info "Stopping Apache and websocket server..."
    if systemctl is-active --quiet ${APACHE_SERVICE}; then
        stop_service ${APACHE_SERVICE}
    else
        warning "Apache is not running. Nothing to do." 1
    fi
    if systemctl is-active --quiet websocket-server; then
        stop_service websocket-server
    else
        warning "Websocket server is not running. Nothing to do." 1
    fi

    info "Disabling websocket server..."
    if [ -f /etc/systemd/system/websocket-server.service ]; then
        if systemctl is-enabled --quiet websocket-server; then
            disable_service websocket-server
        else
            warning "Websocket server is not enabled. Nothing to do." 1
        fi
    else
        warning "Websocket server service file does not exist. Nothing to do." 1
    fi

    info "Removing websocket server service file..."
    rm -f /etc/systemd/system/websocket-server.service

    info "Removing server dashboard files..."
    rm -rf ${APP_DIR}

    info "Removing Apache configuration for SSL if exists..."
    if [ "$OS" = "debian" ]; then
        if [ -f /etc/apache2/sites-enabled/server-dashboard-ssl.conf ]; then
            disable_apache_site server-dashboard-ssl
            rm -f /etc/apache2/sites-available/server-dashboard-ssl.conf
        else
            warning "SSL configuration not found." 1
        fi
        if [ -f /etc/apache2/sites-enabled/server-dashboard.conf ]; then
            disable_apache_site server-dashboard
            rm -f /etc/apache2/sites-available/server-dashboard.conf
        else
            warning "HTTP configuration not found." 1
        fi
    elif [ "$OS" = "redhat" ]; then
        rm -f /etc/httpd/conf.d/server-dashboard.conf
        rm -f /etc/httpd/conf.d/server-dashboard-ssl.conf
    fi
    rm -f /etc/${APACHE_SSL_DIR}/apache.crt /etc/${APACHE_SSL_DIR}/apache.key

    info "Removing sudo rules for services..."
    rm -f /etc/sudoers.d/${WEB_USER}-restart

    info "Restarting Apache to apply changes..."
    if systemctl is-active --quiet ${APACHE_SERVICE}; then
        restart_service ${APACHE_SERVICE}
    else
        warning "Apache is not running. Nothing to do." 1
    fi

    if [ "$FIREWALL" = true ]; then
        info "Removing firewall configuration..."        
        if [ "$OS" = "debian" ]; then
            if command -v ufw &> /dev/null; then
                ufw --force delete allow 80
                ufw --force delete allow 443
                ufw --force disable
            else
                warning "UFW is not installed." 1
            fi
        elif [ "$OS" = "redhat" ]; then
            if command -v firewall-cmd &> /dev/null; then
                firewall-cmd --zone=public --remove-service=http --permanent
                firewall-cmd --zone=public --remove-service=https --permanent
                firewall-cmd --reload
            else
                warning "Firewalld is not installed." 1
            fi
        fi
    fi

    info "Removing crond configuration..."
    rm -f /etc/cron.d/server-dashboard

    info "Removing logrotate configuration..."
    rm -f /etc/logrotate.d/server-dashboard

    info "Removing SELinux configuration..."
    if command -v getenforce &> /dev/null; then
        if semodule -l | grep httpd_systemctl; then
            sudo semodule -r httpd_systemctl
            if semodule -l | grep httpd_systemctl; then
                error "Failed to remove httpd_systemctl SELinux module."
                exit 1
            else
                info "httpd_systemctl SELinux module removed successfully."
            fi
        fi
    fi

    info "Uninstallation complete."
    exit 0
fi

if [ "$INSTALL" = true ]; then
    info "Installing necessary packages..."
    if [ "$OS" = "debian" ]; then
        install_packages apache2 php libapache2-mod-php php-curl
    elif [ "$OS" = "redhat" ]; then
        install_packages httpd php mod_ssl
        systemctl enable ${APACHE_SERVICE}
    fi

    info "Enabling Apache mod_rewrite..."
    enable_apache_module rewrite
    restart_service ${APACHE_SERVICE}

    info "Creating directory for the server dashboard..."
    mkdir -p ${APP_DIR}

    info "Copying files to the server dashboard directory..."
    cp -r src ${APP_DIR}
    cp -r system ${APP_DIR}
    cp .env ${APP_DIR}
    cp composer.json ${APP_DIR}
    mkdir -p ${APP_DIR}/logs

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

        bash -c "cat <<EOF > $CONFIG_PATH
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName localhost
    DocumentRoot /var/www/html

    Alias ${BASE_URL} ${APP_DIR}/src/

    <Directory ${APP_DIR}/src/>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ProxyPreserveHost On
    ProxyPass ${BASE_URL}/api/logs/stream ws://localhost:8080/
    ProxyPassReverse ${BASE_URL}/api/logs/stream ws://localhost:8080/

    ErrorLog ${LOG_DIR}/error.log
    CustomLog ${LOG_DIR}/access.log combined

    
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
        if [ -f /etc/${APACHE_SSL_DIR}/apache.crt ] || [ -f /etc/${APACHE_SSL_DIR}/apache.key ]; then
            warning "SSL certificates already exist. Skipping certificate generation." 1
        else
            openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/${APACHE_SSL_DIR}/apache.key -out /etc/${APACHE_SSL_DIR}/apache.crt -subj "/C=US/ST=State/L=City/O=Organization/OU=Department/CN=your_domain.com"
        fi
        if [ "$OS" = "debian" ]; then
            CONFIG_PATH="/etc/apache2/sites-available/server-dashboard-ssl.conf"
            LOG_DIR="/var/log/apache2"
        elif [ "$OS" = "redhat" ]; then
            CONFIG_PATH="/etc/httpd/conf.d/server-dashboard-ssl.conf"
            LOG_DIR="/var/log/httpd"
        fi
        bash -c "cat <<EOF > $CONFIG_PATH
<VirtualHost *:443>
    ServerAdmin webmaster@localhost
    ServerName localhost
    DocumentRoot /var/www/html

    Alias ${BASE_URL} ${APP_DIR}/src/

    SSLEngine on
    SSLCertificateFile /etc/${APACHE_SSL_DIR}/apache.crt
    SSLCertificateKeyFile /etc/${APACHE_SSL_DIR}/apache.key

    <Directory ${APP_DIR}/src/>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ProxyPreserveHost On
    ProxyPass ${BASE_URL}/api/logs/stream ws://localhost:8080/
    ProxyPassReverse ${BASE_URL}/api/logs/stream ws://localhost:8080/

    ErrorLog ${LOG_DIR}/error.log
    CustomLog ${LOG_DIR}/access.log combined
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

    configure_logrotate

    configure_log_dirs

    info "Installation complete. Please check your server dashboard at http://your_server_ip/server-dashboard"
    if [ "$ENABLE_SSL" = true ]; then
        info "SSL enabled. Access your server dashboard at https://your_domain.com/server-dashboard"
    fi
fi

if [ "$FIREWALL" = true ]; then
    configure_firewall
fi

if [ "$ADD_SUDO_RULES" = true ]; then
    info "Adding sudo rules for services..."

    # Convert comma-separated strings to arrays
    IFS=',' read -r -a services <<< "$SERVICES"

    # Adding apache to sudo
    sudo usermod -aG ${GROUP_SUDO} ${WEB_USER}

    # Add restart and stop rules for each filtered service
    for service in "${services[@]}"; do
        RESTART_RULE="${WEB_USER} ALL=(ALL) NOPASSWD: /bin/systemctl restart $service"
        STOP_RULE="${WEB_USER} ALL=(ALL) NOPASSWD: /bin/systemctl stop $service"

        if sudo grep -Fxq "$RESTART_RULE" /etc/sudoers.d/${WEB_USER}-restart; then
            info "Restart rule for $service already exists in sudoers file."
        else
            echo "$RESTART_RULE" | sudo tee -a /etc/sudoers.d/${WEB_USER}-restart
            sudo chmod 0440 /etc/sudoers.d/${WEB_USER}-restart
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
            sudo chmod 0440 /etc/sudoers.d/${WEB_USER}-restart
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

if [ "$SELINUX" = true ]; then
    configure_selinux
fi
