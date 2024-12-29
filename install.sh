#!/bin/bash

set -e

# Function to display usage
usage() {
    echo "Usage: $0 [--install] [--enable-ssl] [--enable-http] [--uninstall] [--add-sudo-rules] [--remove-sudo-rules]"
    exit 1
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
    systemctl stop apache2
    systemctl stop websocket-server

    echo "Disabling websocket server..."
    systemctl disable websocket-server

    echo "Removing server dashboard files..."
    rm -rf /var/www/html/server-dashboard

    echo "Removing Apache configuration for SSL if exists..."
    a2dissite server-dashboard-ssl || true
    rm -f /etc/apache2/sites-available/default-ssl.conf
    rm -f /etc/apache2/ssl/apache.crt /etc/apache2/ssl/apache.key

    echo "Removing sudo rules for services..."
    rm -f /etc/sudoers.d/www-data-restart

    echo "Restarting Apache to apply changes..."
    systemctl restart apache2

    echo "Uninstallation complete."
    exit 0
fi

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

    echo "Updating package list..."
    apt-get update

    echo "Installing necessary packages..."
    apt-get install -y apache2 php libapache2-mod-php

    echo "Enabling Apache mod_rewrite..."
    a2enmod rewrite
    systemctl restart apache2

    echo "Creating directory for the server dashboard..."
    mkdir -p /var/www/html/server-dashboard

    echo "Copying files to the server dashboard directory, including hidden files..."
    shopt -s dotglob
    cp -r * /var/www/html/server-dashboard/
    shopt -u dotglob

    echo "Setting the correct permissions..."
    chown -R www-data:www-data /var/www/html/server-dashboard
    chmod -R 755 /var/www/html/server-dashboard

    echo "Restarting Apache to apply changes..."
    systemctl restart apache2
    echo "Copying websocket server service file to /etc/systemd/system..."
    cp /var/www/html/server-dashboard/system/websocket-server.service /etc/systemd/system/

    echo "Enabling and starting websocket server..."
    systemctl enable websocket-server
    systemctl start websocket-server

    echo "Installing Composer dependencies..."
    cd /var/www/html/server-dashboard
    apt-get install -y composer
    composer install

    if [ "$ENABLE_HTTP" = true ]; then
        echo "Setting up HTTP configuration..."
        bash -c 'cat <<EOF > /etc/apache2/sites-available/server-dashboard.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/server-dashboard/public

    <Directory /var/www/html/server-dashboard/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ProxyPreserveHost On
    ProxyPass /api/logs/stream ws://localhost:8080/
    ProxyPassReverse /api/logs/stream ws://localhost:8080/

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined

    
EOF'
        if [ "$ENABLE_SSL" = true ]; then
            bash -c 'cat <<EOF > /etc/apache2/sites-available/000-default.conf
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
EOF'
        fi
        bash -c 'cat <<EOF >> /etc/apache2/sites-available/server-dashboard.conf
</VirtualHost>
EOF'


        a2ensite server-dashboard
        systemctl reload apache2
    fi

    if [ "$ENABLE_SSL" = true ]; then
        echo "Enabling SSL and generating self-signed certificates..."
        apt-get install -y openssl
        a2enmod ssl
        mkdir -p /etc/apache2/ssl
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/apache2/ssl/apache.key -out /etc/apache2/ssl/apache.crt -subj "/C=US/ST=State/L=City/O=Organization/OU=Department/CN=your_domain.com"
        bash -c 'cat <<EOF > /etc/apache2/sites-available/server-dashboard-ssl.conf
<VirtualHost *:443>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/server-dashboard/public

    SSLEngine on
    SSLCertificateFile /etc/apache2/ssl/apache.crt
    SSLCertificateKeyFile /etc/apache2/ssl/apache.key

    <Directory /var/www/html/server-dashboard/public>
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
EOF'
        a2enmod ssl proxy proxy_wstunnel
        a2ensite server-dashboard-ssl
        systemctl reload apache2
    fi

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

    # Add restart rule for each filtered service
    for service in "${services[@]}"; do
        RULE="www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart $service"
        if sudo grep -Fxq "$RULE" /etc/sudoers; then
            echo "Rule for $service already exists in sudoers file."
        else
            echo "$RULE" | sudo tee -a /etc/sudoers.d/www-data-restart
            sudo visudo -cf /etc/sudoers.d/www-data-restart
            if [ $? -eq 0 ]; then
                echo "Rule for $service added successfully."
            else
                echo "Failed to add rule for $service. Please check the sudoers file syntax."
                sudo rm /etc/sudoers.d/www-data-restart
            fi
        fi
    done

    echo "Sudo rules added successfully."
    exit 0
fi

if [ "$REMOVE_SUDO_RULES" = true ]; then
    echo "Removing sudo rules for services..."
    rm -f /etc/sudoers.d/www-data-restart
    echo "Sudo rules removed successfully."
    exit 0
fi