#!/bin/bash

set -e

# Function to display usage
usage() {
    echo "Usage: $0 [--install] [--enable-ssl] [--enable-http] [--uninstall] [--add-sudo-rules] [--remove-sudo-rules]"
    exit 1
}

# Load environment variables
source .env

APP_DIR=${APP_DIR:-/var/www/html/server-dashboard}

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
        echo "Unsupported operating system."
        exit 1
    fi
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
    elif [ "$OS" = "redhat" ]; then
        ln -s /etc/httpd/conf.d/"$1".conf /etc/httpd/conf.d/"$1".conf
    fi
}

# Function to configure SELinux
configure_selinux() {
    if command -v getenforce &> /dev/null; then
        if [ "$(getenforce)" != "Disabled" ]; then
            echo "Configuring SELinux policies..."

            # Allow Apache to connect to the network
            setsebool -P httpd_can_network_connect 1

            # Allow Apache to connect to the WebSocket server
            setsebool -P httpd_can_network_relay 1

            # Allow Apache to read and write to the app directory
            semanage fcontext -a -t httpd_sys_rw_content_t "${APP_DIR}(/.*)?"
            restorecon -Rv ${APP_DIR}

            echo "SELinux configuration complete."
        else
            echo "SELinux is disabled."
        fi
    else
        echo "SELinux is not installed."
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

# Check if the script is run as root
if [ "$EUID" -ne 0 ]; then
    echo "This script must be run as root."
    exit 1
fi

# Check if no arguments are provided
if [ "$#" -eq 0 ]; then
    usage
fi

# Detect the operating system
detect_os

# Parse arguments
ENABLE_SSL=false
ENABLE_HTTP=false
UNINSTALL=false
INSTALL=false
ADD_SUDO_RULES=false
REMOVE_SUDO_RULES=false
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --install) INSTALL=true ;;
        --enable-ssl) ENABLE_SSL=true ;;
        --enable-http) ENABLE_HTTP=true ;;
        --uninstall) UNINSTALL=true ;;
        --add-sudo-rules) ADD_SUDO_RULES=true ;;
        --remove-sudo-rules) REMOVE_SUDO_RULES=true ;;
        *) usage ;;
    esac
    shift
done

if [ "$UNINSTALL" = true ]; then
    echo "Uninstalling server dashboard..."

    echo "Stopping Apache and websocket server..."
    stop_service ${APACHE_SERVICE}
    stop_service websocket-server

    echo "Disabling websocket server..."
    disable_service websocket-server

    echo "Removing server dashboard files..."
    rm -rf ${APP_DIR}

    echo "Removing Apache configuration for SSL if exists..."
    if [ "$OS" = "debian" ]; then
        rm -f /etc/apache2/sites-available/default-ssl.conf
    elif [ "$OS" = "redhat" ]; then
        rm -f /etc/httpd/conf.d/default-ssl.conf
    fi
    rm -f /etc/${APACHE_SSL_DIR}/apache.crt /etc/${APACHE_SSL_DIR}/apache.key

    echo "Removing sudo rules for services..."
    rm -f /etc/sudoers.d/${WEB_USER}-restart

    echo "Restarting Apache to apply changes..."
    restart_service ${APACHE_SERVICE}

    echo "Uninstallation complete."
    exit 0
fi

configure_logrotate() {
    echo "Configuring logrotate..."

    # Install logrotate if not already installed
    install_packages logrotate

    # Load environment variables
    source .env

    LOG_PATH=${LOG_DIR:-${APP_DIR}/logs}

    bash -c "cat <<EOF > /etc/logrotate.d/server-dashboard
$LOG_PATH/*.log {
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

    echo "Logrotate configuration complete."

    # Reload logrotate configuration if needed
    systemctl restart logrotate.timer
}

if [ "$INSTALL" = true ]; then
    # Check for .env file
    if [ ! -f .env ]; then
        echo ".env file is missing."
        echo "Please create it by copying the .env.example file:"
        echo "cp .env.example .env"
        exit 1
    fi

    if [ ! -d public ] || [ ! -f composer.json ]; then
        echo "public folder or composer.json file is missing."
        echo "Please make sure you are running the script in the correct directory."
        exit 1
    fi

    echo "Installing necessary packages..."
    if [ "$OS" = "debian" ]; then
        install_packages apache2 php libapache2-mod-php
    elif [ "$OS" = "redhat" ]; then
        install_packages httpd php mod_ssl
    fi

    echo "Enabling Apache mod_rewrite..."
    enable_apache_module rewrite
    restart_service ${APACHE_SERVICE}

    echo "Creating directory for the server dashboard..."
    mkdir -p ${APP_DIR}

    echo "Copying files to the server dashboard directory, including hidden files..."
    shopt -s dotglob
    cp -r * ${APP_DIR}
    shopt -u dotglob

    echo "Setting the correct permissions..."
    chown -R ${WEB_USER}:${WEB_USER} ${APP_DIR}
    chmod -R 755 ${APP_DIR}

    echo "Restarting Apache to apply changes..."
    restart_service ${APACHE_SERVICE}
    echo "Copying websocket server service file to /etc/systemd/system..."
    cp ${APP_DIR}/system/websocket-server.service /etc/systemd/system/

    echo "Replacing placeholders in websocket server service file..."
    sed -i "s|{{APP_DIR}}|${APP_DIR}|g" /etc/systemd/system/websocket-server.service

    echo "Installing Composer dependencies..."
    cd ${APP_DIR}
    install_packages composer
    composer install

    if [ "$ENABLE_HTTP" = true ]; then
        echo "Setting up HTTP configuration..."
        if [ "$OS" = "debian" ]; then
            CONFIG_PATH="/etc/apache2/sites-available/server-dashboard.conf"
        elif [ "$OS" = "redhat" ]; then
            CONFIG_PATH="/etc/httpd/conf.d/server-dashboard.conf"
        fi
        bash -c "cat <<EOF > $CONFIG_PATH
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ProxyPreserveHost On
    ProxyPass /api/logs/stream ws://localhost:8080/
    ProxyPassReverse /api/logs/stream ws://localhost:8080/

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined

    
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
        echo "Enabling SSL and generating self-signed certificates..."
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

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF"
        enable_apache_module proxy
        enable_apache_module proxy_wstunnel
        enable_apache_site server-dashboard-ssl
        systemctl reload ${APACHE_SERVICE}
    fi
    
    echo "Enabling and starting websocket server..."
    enable_service websocket-server
    start_service websocket-server

    configure_logrotate

    configure_selinux

    configure_log_dirs

    echo "Installation complete. Please check your server dashboard at http://your_server_ip/server-dashboard"
    if [ "$ENABLE_SSL" = true ]; then
        echo "SSL enabled. Access your server dashboard at https://your_domain.com/server-dashboard"
    fi
fi

if [ "$ADD_SUDO_RULES" = true ]; then
    echo "Adding sudo rules for services..."

    # Load environment variables
    source .env

    # Convert comma-separated strings to arrays
    IFS=',' read -r -a services <<< "$SERVICES"

    # Add restart and stop rules for each filtered service
    for service in "${services[@]}"; do
        RESTART_RULE="${WEB_USER} ALL=(ALL) NOPASSWD: /bin/systemctl restart $service"
        STOP_RULE="${WEB_USER} ALL=(ALL) NOPASSWD: /bin/systemctl stop $service"
        
        if sudo grep -Fxq "$RESTART_RULE" /etc/sudoers.d/${WEB_USER}-restart; then
            echo "Restart rule for $service already exists in sudoers file."
        else
            echo "$RESTART_RULE" | sudo tee -a /etc/sudoers.d/${WEB_USER}-restart
            sudo visudo -cf /etc/sudoers.d/${WEB_USER}-restart
            if [ $? -eq 0 ]; then
                echo "Restart rule for $service added successfully."
            else
                echo "Failed to add restart rule for $service. Please check the sudoers file syntax."
                sudo rm /etc/sudoers.d/${WEB_USER}-restart
            fi
        fi

        if sudo grep -Fxq "$STOP_RULE" /etc/sudoers.d/${WEB_USER}-restart; then
            echo "Stop rule for $service already exists in sudoers file."
        else
            echo "$STOP_RULE" | sudo tee -a /etc/sudoers.d/${WEB_USER}-restart
            sudo visudo -cf /etc/sudoers.d/${WEB_USER}-restart
            if [ $? -eq 0 ]; then
                echo "Stop rule for $service added successfully."
            else
                echo "Failed to add stop rule for $service. Please check the sudoers file syntax."
                sudo rm /etc/sudoers.d/${WEB_USER}-restart
            fi
        fi
    done

    echo "Sudo rules added successfully."
    exit 0
fi


if [ "$REMOVE_SUDO_RULES" = true ]; then
    echo "Removing sudo rules for services..."
    rm -f /etc/sudoers.d/${WEB_USER}-restart
    echo "Sudo rules removed successfully."
    exit 0
fi