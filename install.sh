#!/bin/bash

set -e

# Function to display usage
usage() {
    echo "Usage: $0 [--enable-ssl] [--uninstall]"
    exit 1
}

# Check if the script is run as root
if [ "$EUID" -ne 0 ]; then
    echo "This script must be run as root."
    exit 1
fi

# Parse arguments
ENABLE_SSL=false
UNINSTALL=false
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --enable-ssl) ENABLE_SSL=true ;;
        --uninstall) UNINSTALL=true ;;
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
    a2dissite default-ssl.conf || true
    rm -f /etc/apache2/sites-available/default-ssl.conf
    rm -f /etc/apache2/ssl/apache.crt /etc/apache2/ssl/apache.key

    echo "Restarting Apache to apply changes..."
    systemctl restart apache2

    echo "Uninstallation complete."
    exit 0
fi

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

echo "Copying files to the server dashboard directory..."
cp -r * /var/www/html/server-dashboard/

echo "Setting the correct permissions..."
chown -R www-data:www-data /var/www/html/server-dashboard
chmod -R 755 /var/www/html/server-dashboard

echo "Restarting Apache to apply changes..."
systemctl restart apache2

echo "Enabling system/websocket-server..."
# Assuming you have a script or command to enable the websocket server
# Replace the following line with the actual command to enable the websocket server
systemctl enable websocket-server
systemctl start websocket-server

echo "Installing Composer dependencies..."
cd /var/www/html/server-dashboard
apt-get install -y composer
composer install

if [ "$ENABLE_SSL" = true ]; then
    echo "Enabling SSL and generating self-signed certificates..."
    apt-get install -y openssl
    a2enmod ssl
    mkdir -p /etc/apache2/ssl
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/apache2/ssl/apache.key -out /etc/apache2/ssl/apache.crt -subj "/C=US/ST=State/L=City/O=Organization/OU=Department/CN=your_domain.com"
    bash -c 'cat <<EOF > /etc/apache2/sites-available/default-ssl.conf
<VirtualHost *:443>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/server-dashboard

    SSLEngine on
    SSLCertificateFile /etc/apache2/ssl/apache.crt
    SSLCertificateKeyFile /etc/apache2/ssl/apache.key

    <Directory /var/www/html/server-dashboard>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF'
    a2enmod ssl
    a2ensite default-ssl
    systemctl reload apache2
fi

echo "Installation complete. Please check your server dashboard at http://your_server_ip/server-dashboard"
if [ "$ENABLE_SSL" = true ]; then
    echo "SSL enabled. Access your server dashboard at https://your_domain.com/server-dashboard"
fi