#!/bin/bash

if [[ "$1" =~ ^(start|stop|restart)$ ]] && [[ "$2" =~ ^[a-zA-Z0-9\._-]+$ ]]; then
    systemctl "$1" "$2"
else
    echo "Usage: $0 {start|stop|restart} service_name"
    exit 1
fi

