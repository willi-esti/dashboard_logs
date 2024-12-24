// Populate service list
function populateServiceList(services) {
    const serviceList = document.getElementById('serviceList');
    serviceList.innerHTML = ''; // Clear existing services
    console.log(services);
    
    services.forEach(service => {
        const serviceCard = document.createElement('div');
        let active = false;
        if (service.status == '1') {
            active = true;
        }
        console.log(active);
        serviceCard.className = 'col-md-4';
        serviceCard.innerHTML = `
            <div class="card shadow-sm">
                <div class="card-body">
                    <span class="status-indicator ${active ? 'status-active' : 'status-inactive'}"></span>
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
        const logItem = document.createElement('div');
        
        logItem.className = 'col-md-4';
        logItem.innerHTML = `
                <div class="card shadow-sm">
                    <div class="card-body">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
                            <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5"/>
                            <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z"/>
                        </svg>
                        <strong>${file}</strong>
                        <div class="mt-2">
                            <button class="btn btn-primary btn-sm" onclick="downloadLogFile('${file}')">Download</button>
                            <button class="btn btn-success btn-sm" onclick="viewLog('${file}')">View</button>
                        </div>
                    </div>
                </div>
        `;
        
        logFileList.appendChild(logItem);

    });
}
document.addEventListener('DOMContentLoaded', () => {
    let toggleScrollButton = document.getElementById('toggleScrollButton');
    // this tiggoel will disbale white-space: pre-wrap
    toggleScrollButton.addEventListener('click', () => {
        //console.log(logContent.style.whiteSpace);
        let logContent = document.getElementById('logContent').children[0];
        if (logContent.style.whiteSpace === 'pre') {
            logContent.style.whiteSpace = 'pre-wrap';
            toggleScrollButton.textContent = 'Enable Scroll';
        } else {
            logContent.style.whiteSpace = 'pre';
            toggleScrollButton.textContent = 'Disable Scroll';
        }
    });
    /*toggleScrollButton.addEventListener('click', () => {
        if (logContent.style.overflowY === 'scroll') {
            logContent.style.overflowY = 'visible';
            logContent.style.height = 'auto';
            toggleScrollButton.textContent = 'Enable Scroll';
        } else {
            logContent.style.overflowY = 'scroll';
            logContent.style.height = '400px'; // Set a fixed height for scrolling
            toggleScrollButton.textContent = 'Disable Scroll';
        }
    });*/
});

async function downloadLogFile(fileName) {
    const response = await fetch(`/server-dashboard/public/api/logs/download?file=${fileName}`);
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
}

// View selected log content
async function viewLog(fileName) {
    const response = await fetchLogContent(fileName);
    const logContent = document.getElementById('logContent');
    
    // wrap each line of the content in a <code>
    logContent.innerHTML = `<pre>${response.content.split('\n').map(line => `<code>${line}</code>`).join('\n') || 'Error fetching log content.'}</pre>`;
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
