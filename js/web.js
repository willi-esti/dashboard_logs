// Update the last updated timestamp
document.getElementById('lastUpdate').innerText = new Date().toLocaleString();

// Add new service functionality
const serviceList = document.getElementById('serviceList');
const addServiceForm = document.getElementById('addServiceForm');

addServiceForm.addEventListener('submit', (e) => {
    e.preventDefault();

    const serviceName = document.getElementById('serviceName').value;
    if (serviceName) {
        const serviceCard = document.createElement('div');
        serviceCard.className = 'col-md-4';
        serviceCard.innerHTML = `
            <div class="card shadow-sm">
                <div class="card-body">
                    <span class="status-indicator status-inactive"></span>
                    <strong>${serviceName}</strong>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary">Restart</button>
                        <button class="btn btn-sm btn-secondary">Stop</button>
                        <button class="btn btn-sm btn-danger remove-service">Remove</button>
                    </div>
                </div>
            </div>
        `;
        serviceList.appendChild(serviceCard);
        document.getElementById('serviceName').value = '';

        // Add remove functionality
        serviceCard.querySelector('.remove-service').addEventListener('click', () => {
            serviceCard.remove();
        });
    }
});

// Populate log files dynamically (mockup example)
const logFiles = ["server.log", "errors.log", "access.log"];
const logFileList = document.getElementById('logFileList');
const logContent = document.getElementById('logContent');

logFiles.forEach(file => {
    const listItem = document.createElement('li');
    listItem.innerHTML = `
        <button class="btn btn-link" onclick="showLogContent('${file}')">${file}</button>
        <button class="btn btn-sm btn-secondary" onclick="downloadLog('${file}')">Download</button>
    `;
    logFileList.appendChild(listItem);
});

function showLogContent(file) {
    // Mockup content (replace with actual fetch logic)
    const mockContent = {
        "server.log": "Server started at 12:00 PM\nListening on port 8080...",
        "errors.log": "[Error] Failed to connect to database\n[Error] Unauthorized access attempt detected",
        "access.log": "192.168.0.1 - - [20/Dec/2024:12:00:00 +0000] \"GET /index.html HTTP/1.1\" 200 1024"
    };

    logContent.hidden = false;
    logContent.textContent = mockContent[file] || "Log content not found.";
}

function downloadLog(file) {
    // Replace with actual download logic
    alert(`Downloading: ${file}`);
}

// Remove service functionality
document.querySelectorAll('.remove-service').forEach(button => {
    button.addEventListener('click', (e) => {
        e.target.closest('.col-md-4').remove();
    });
});

addServiceForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const serviceName = document.getElementById('serviceName').value;

    if (serviceName) {
        fetch('add_service.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `name=${encodeURIComponent(serviceName)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                loadServices();
                document.getElementById('serviceName').value = '';
            } else {
                alert('Error adding service.');
            }
        });
    }
});

function loadServices() {
    fetch('fetch_services.php')
        .then(response => response.json())
        .then(services => {
            serviceList.innerHTML = '';
            services.forEach(service => {
                const serviceCard = document.createElement('div');
                serviceCard.className = 'col-md-4';
                serviceCard.innerHTML = `
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <span class="status-indicator status-inactive"></span>
                            <strong>${service.name}</strong>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-primary">Restart</button>
                                <button class="btn btn-sm btn-secondary">Stop</button>
                                <button class="btn btn-sm btn-danger" onclick="removeService(${service.id})">Remove</button>
                            </div>
                        </div>
                    </div>
                `;
                serviceList.appendChild(serviceCard);
            });
        });
}
loadServices();


function removeService(id) {
    fetch('remove_service.php', {
        method: 'DELETE',
        body: `id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            loadServices();
        } else {
            alert('Error removing service.');
        }
    });
}


function showLogContent(file) {
    logContent.hidden = false;
    logContent.textContent = ""; // Clear previous content

    function fetchLog() {
        fetch(`stream-log.php?file=${file}`)
            .then(response => response.text())
            .then(data => {
                logContent.textContent += data; // Append new lines
                logContent.scrollTop = logContent.scrollHeight; // Auto-scroll to the bottom
                fetchLog(); // Continuously fetch updates
            })
            .catch(error => {
                logContent.textContent += "\n[Error fetching log content]";
            });
    }

    fetchLog();
}
