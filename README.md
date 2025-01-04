# Server Dashboard

Server Dashboard is a web application that allows users to manage server services and view log files. It provides functionalities to view status, restart and stop services, as well as view and download log files.

![Server Dashboard](server-dashboard-dark.jpeg)

## Features

- **Service Management**:
  - View the status of services (e.g., nginx, mysql, apache2).
  - Add new services. (Upcoming)
  - Restart or stop services.

- **Log Viewing**:
  - View log files from the server.
  - Download log files.
  - Stream log content in real-time.
  - Ability to add multiple log directories.

- **WebSocket Integration**:
  - Real-time updates for service status and log streaming.

## API Endpoints

### Authentification

- `POST /api/authenticate`: Authenticate the user via the Authorization header, Format : Basic user:password (encoded in base64)

### Service Management

- `GET /api/services`: List all services and their statuses.
- `POST /api/services`: Restart or stop a specific service.

### Log Management

- `GET /api/logs`: List available log files.
- `GET /api/logs/download?file=name`: Download a specific log file.
- `GET /api/logs/stream?token={token}&logFile={filename}`: Stream log content in real-time.

### Reports, only in Selinux mode

- `GET /api/reports` : List the restart and stop actions done and delete them from the json
- `GET /api/reports?debug=true` : List the restart and stop actions pending and done, without deleting them

## Installation

1. Clone the repository:
  ```sh
  git clone https://github.com/willi-esti/server-dashboard.git
  cd server-dashboard
  ```

2. Configure the environment variables in a [.env](.env.example) file.
3. Run the installation script with the desired options:
  Recommended for full functionalities:
  ```sh
  chmod u+x install.sh # Make the script executable 
  sudo ./install.sh --install --enable-http --enable-ssl --add-sudo-rules # you should disable 000-default after the installation (a2dissite 000-default)
  ```

  Alternatively, you can use the following options:
  ```sh
  sudo ./install.sh [--install] [--enable-ssl] [--enable-http] [--uninstall] [--add-sudo-rules] [--remove-sudo-rules] [--selinux]
  ```

  - `--install`: Install the server dashboard and run the websocket-server service. Install necessary packages, enable Apache modules, configure SELinux policies (if Enforcing), configure logrotate, configure crond (if Selinux is Enforcing), and configure firewall. (The project will be in {{APP_DIR}} configured in the .env)
  - `--enable-ssl`: Enable SSL and generate self-signed certificates. (The file server-dashboard-ssl.conf will be added to sites-available in the apache conf)
  - `--enable-http`: Set up HTTP configuration. (The file server-dashboard.conf will be added to sites-available in the apache conf)
  - `--uninstall`: Uninstall the server dashboard, remove SELinux configuration, logrotate configuration, cron configuration, and firewall configuration
  - `--add-sudo-rules`: Adds sudo rules for specified services in the variable SERVICE of your .env. (File with all the rules: /etc/sudoers.d/www-data-restart)
  - `--remove-sudo-rules`: Removes sudo rules for specified services.
  - `--selinux`: When SELinux is on, a cron job will be set up in the install script to manage the restart and stop of services, which delays the restarts by a few seconds to 1 minute. This SELinux mode will be activated automatically if it's in the Enforcing mode during the `--install`. You can run `--selinux` to regenerate new SELinux policies if you are having issues showing the status of the services.

4. Make sure the WebSocket server daemon:
  ```sh
  sudo systemctl status websocket-server
  ```

5. Access the dashboard from your browser (https://yourserver.ip/)

## Usage

- **Add a Service**: Use the .env to add a new service. (like in the .env.example)
- **Manage Services**: Use the buttons next to each service to view the status, restart or stop the service.
- **View Logs**: Click on view log in the "Logs" section to view its content. Use the "Download" button to download the log file.

## Contributing

Contributions are welcome! Please open an issue or submit a pull request for any improvements or bug fixes.

## License

This project is licensed under the MIT License.
