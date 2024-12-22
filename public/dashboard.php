<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/web.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h1 class="text-center mb-4">Server Dashboard</h1>

    <!-- Services Section -->
    <section>
        <h2>Service Status</h2>
        <div class="row" id="serviceList">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <span class="status-indicator status-active"></span>
                        <strong>nginx</strong>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary">Restart</button>
                            <button class="btn btn-sm btn-secondary">Stop</button>
                            <button class="btn btn-sm btn-danger remove-service">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <h3>Add New Service</h3>
            <form id="addServiceForm" class="d-flex">
                <input type="text" id="serviceName" class="form-control me-2" placeholder="Service Name" required>
                <button type="submit" class="btn btn-success">Add Service</button>
            </form>
        </div>
    </section>

    <!-- Logs Section -->
    <section class="mt-4">
        <h2>Logs</h2>
        <div class="log-view">
            <ul id="logFileList" class="list-unstyled">
                <!-- Log files will be populated here -->
            </ul>
        </div>
        <div id="logContent" class="log-content" hidden>
            <p>Select a log file to view its content.</p>
        </div>
    </section>

    <!-- Footer -->
    <div class="footer">
        <p>Last updated: <span id="lastUpdate">2024-12-20 12:00:00</span></p>
    </div>

    <script src="js/web.js"></script>
    <script src="js/fetching.js"></script>
</body>
</html>