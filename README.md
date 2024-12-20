# Server Dashboard

Server Dashboard is a web application that allows users to manage server services and view log files. It provides functionalities to add, remove, restart, and stop services, as well as view and download log files.

## Features

- **Service Management**:
  - View the status of services (e.g., nginx, mysql, apache2).
  - Add new services.
  - Restart, stop, or remove services.

- **Log Viewing**:
  - View log files from the server.
  - Download log files.
  - Stream log content in real-time.

## Installation prerequired

```sh
apt install php-sqlite3 sqlite3 composer
```

## Installation

1. Clone the repository:
    ```sh
    git clone https://github.com/willi-esti/server-dashboard.git
    cd server-dashboard
    ```

2. Set up the database:
    ```sh
    sqlite3 dashboard.db < schema.sql
    ```

3. Configure the environment variables in a [.env](https://github.com/willi-esti/server-dashboard/blob/master/.env.example) file.

4. Start the server:
    ```sh
    php -S localhost:8000
    ```
    ```sh
    php -S 0.0.0.0:8000
    ```

5. Open your browser and navigate to `http://localhost:8000`.

## Usage

- **Add a Service**: Use the form in the "Service Status" section to add a new service.
- **Manage Services**: Use the buttons next to each service to restart, stop, or remove the service.
- **View Logs**: Click on a log file name in the "Logs" section to view its content. Use the "Download" button to download the log file.

## Contributing

Contributions are welcome! Please open an issue or submit a pull request for any improvements or bug fixes.

## License

This project is licensed under the MIT License.
