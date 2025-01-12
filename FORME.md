# CURL
```
curl -kv -H "Authorization: Bearer BDfsdP5d575xd4xsdpBr9eD1XWe5" -X GET https://192.168.56.103/dashboard/api/authenticate
```

# Rights

```
find /var/www/html/server-dashboard -type f -exec chmod 660 {} \;
find /var/www/html/server-dashboard -type d -exec chmod 770 {} \;
```

# For websocket debugging

```
npm install -g wscat
wscat -c ws://localhost:8080?token=YOUR_JWT_TOKEN
```

# Logrotate
```
nano /etc/logrotate.d/websocker-server
```

```
/var/www/html/server-dashboard/logs/*.log {
    size 50M              # Rotate logs when they exceed 50 MB
    rotate 5              # Keep 5 old log files
    compress              # Compress old log files
    delaycompress         # Delay compression until the next rotation
    missingok             # Skip rotation if log files are missing
    notifempty            # Skip rotation if log files are empty
    copytruncate          # Truncate the log file after copying it
    create 0640 www-data www-data # Set permissions for new log files
}
```

# Debug 

Show the log set to rotate
```
cat /var/lib/logrotate/status
```
Verify the conf
```
sudo logrotate -v /etc/logrotate.d/websocker-server
```
Run logrotate
```
sudo logrotate -f /etc/logrotate.d/websocker-server
```


