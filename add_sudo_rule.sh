#!/bin/bash

# Load environment variables
source /var/www/html/server-dashboard/.env

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