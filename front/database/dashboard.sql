-- Create tables for services and users
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS servers (
    id INTEGER PRIMARY KEY,
    name TEXT UNIQUE NOT NULL,
    server_id TEXT NOT NULL,
    ssl BOOLEAN NOT NULL DEFAULT 0,
    ip TEXT NOT NULL,
    port INTEGER NULL,
    path TEXT NOT NULL,
    token TEXT NOT NULL
);

-- Insert admin user with SHA-256 hashed password
-- change me : use `php -r "echo password_hash('password', PASSWORD_DEFAULT);"`
INSERT or IGNORE INTO users (id, username, password) VALUES (1, 'admin', '$2y$10$uJ07JS/ED4sfFEQNEgeW/eaqC2L./q9s2vhooaScvUeG4VKCDQw/e');

-- Insert servers
INSERT or IGNORE INTO servers (id, name, server_id, ssl, ip, port, path, token) VALUES (1, 'Server 1', 'server1', 1, '192.168.56.103', 443, 'dashboard', 'BDfsdP5d575xd4xsdpBr9eD1XWe5');
INSERT or IGNORE INTO servers (id, name, server_id, ssl, ip, port, path, token) VALUES (2, 'Server 2', 'server2', 1, '192.168.56.102', 443, 'dashboard', 'BDfsdP5d575xd4xsdpBr9eD1XWe5');
