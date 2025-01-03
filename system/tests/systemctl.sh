#!/bin/bash

if [[ "$1" =~ ^(start|stop|restart|status)$ ]] && [[ "$2" =~ ^[a-zA-Z0-9\._-]+$ ]]; then
    if [[ "$1" == "status" ]]; then
        /usr/bin/systemctl "$1" "$2"
    else
        sudo /usr/bin/systemctl "$1" "$2"
    fi
else
    echo "Usage: $0 {start|status|stop|restart} service_name"
    exit 1
fi
