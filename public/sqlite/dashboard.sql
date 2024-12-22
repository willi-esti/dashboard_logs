-- Create tables for services and users
CREATE TABLE services (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
);

CREATE TABLE api_logs (
    id INTEGER PRIMARY KEY,
    endpoint TEXT NOT NULL,
    method TEXT NOT NULL,
    user TEXT,
    request TEXT NOT NULL,
    response TEXT NOT NULL,
    status_code INTEGER NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- Insert admin user with SHA-256 hashed password
INSERT INTO users (username, password) VALUES ('admin', '$2y$10$W./gnGxnpZNsltNsMaIdvO0/mizcVx4lKBvKIymHduORSS8fmckaW');

-- Insert services
INSERT INTO services (name, description) VALUES ('httpd', 'HTTP Server');
INSERT INTO services (name, description) VALUES ('apache', 'Apache HTTP Server');
INSERT INTO services (name, description) VALUES ('crond', 'Cron Daemon');
INSERT INTO services (name, description) VALUES ('docker', 'Docker Daemon');


