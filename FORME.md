

```
find /var/www/html/server-dashboard -type f -exec chmod 660 {} \;
find /var/www/html/server-dashboard -type d -exec chmod 770 {} \;
```

For websocket debugging

```
npm install -g wscat
wscat -c ws://localhost:8080?token=YOUR_JWT_TOKEN
```

