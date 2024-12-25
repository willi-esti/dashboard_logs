
#!/bin/bash

# Define the rule to be added
RULE="www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart *"

# Check if the rule already exists
if sudo grep -Fxq "$RULE" /etc/sudoers; then
    echo "Rule already exists in sudoers file."
else
    # Add the rule to the sudoers file
    echo "$RULE" | sudo tee -a /etc/sudoers.d/www-data-restart
    # Validate the sudoers file
    sudo visudo -cf /etc/sudoers.d/www-data-restart
    if [ $? -eq 0 ]; then
        echo "Rule added successfully."
    else
        echo "Failed to add rule. Please check the sudoers file syntax."
        sudo rm /etc/sudoers.d/www-data-restart
    fi
fi