#!/bin/bash

set -e

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
sudo apt-get update

echo "Installing necessary packages..."
sudo apt-get install -y apache2 php libapache2-mod-php

echo "Enabling Apache mod_rewrite..."
sudo a2enmod rewrite
sudo systemctl restart apache2

echo "Creating directory for the server dashboard..."
sudo mkdir -p /var/www/html/server-dashboard

echo "Copying files to the server dashboard directory..."
sudo cp -r * /var/www/html/server-dashboard/

echo "Setting the correct permissions..."
sudo chown -R www-data:www-data /var/www/html/server-dashboard
sudo chmod -R 755 /var/www/html/server-dashboard

echo "Restarting Apache to apply changes..."
sudo systemctl restart apache2

echo "Enabling system/websocket-server..."
# Assuming you have a script or command to enable the websocket server
# Replace the following line with the actual command to enable the websocket server
sudo systemctl enable websocket-server
sudo systemctl start websocket-server

echo "Installing Composer dependencies..."
cd /var/www/html/server-dashboard
sudo apt-get install -y composer
sudo composer install

echo "Installation complete. Please check your server dashboard at http://your_server_ip/server-dashboard"