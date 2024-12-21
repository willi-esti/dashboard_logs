
// Web.js: Handles DOM interactions and updates dynamically

// Populate service list
function populateServiceList(services) {
    const serviceList = document.getElementById('serviceList');
    serviceList.innerHTML = ''; // Clear existing services

    services.forEach(service => {
        const serviceCard = document.createElement('div');
        serviceCard.className = 'col-md-4';
        serviceCard.innerHTML = `
            <div class="card shadow-sm">
                <div class="card-body">
                    <span class="status-indicator ${service.active ? 'status-active' : 'status-inactive'}"></span>
                    <strong>${service.name}</strong>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary" onclick="restartService('${service.name}')">Restart</button>
                        <button class="btn btn-sm btn-secondary" onclick="stopService('${service.name}')">Stop</button>
                        <button class="btn btn-sm btn-danger" onclick="removeService('${service.name}')">Remove</button>
                    </div>
                </div>
            </div>
        `;
        serviceList.appendChild(serviceCard);
    });
}

// Add new service
document.getElementById('addServiceForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const serviceName = document.getElementById('serviceName').value;
    const response = await addService(serviceName);
    if (response.success) {
        alert('Service added successfully!');
        fetchServices();
    } else {
        alert('Error adding service: ' + response.error);
    }
});

// Fetch and display logs
function populateLogFiles(logFiles) {
    const logFileList = document.getElementById('logFileList');
    logFileList.innerHTML = ''; // Clear existing logs

    logFiles.forEach(file => {
        const logItem = document.createElement('li');
        logItem.innerHTML = `<a href="#" onclick="viewLog('${file}')">${file}</a>`;
        logFileList.appendChild(logItem);
    });
}

// View selected log content
async function viewLog(fileName) {
    const response = await fetchLogContent(fileName);
    const logContent = document.getElementById('logContent');
    logContent.hidden = false;
    logContent.innerHTML = `<pre>${response.content || 'Error fetching log content.'}</pre>`;
}

// Initial data fetch
async function fetchServices() {
    const services = await getServices();
    populateServiceList(services);
}

async function fetchLogs() {
    const logFiles = await getLogFiles();
    populateLogFiles(logFiles);
}

// Run initial fetch
fetchServices();
fetchLogs();
