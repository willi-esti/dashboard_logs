function getServerStatus(server) {
    if (server.ssl == 1) {
        server.url = `https://${server.ip}`;
    } else {
        server.url = `http://${server.ip}`;
    }
    if (server.port) {
        server.url += `:${server.port}`;
    }
    server.url += '/' + server.base_url;
    // TODO : check if the server is online
    fetch(`${server.url}/api/authenticate`, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${server.token}`
        }
    })
    .then(response => {
        if (response.ok) {
            server.status = '1';
            // get token from response
            console.log(response);
            response.json().then(data => {
                server.token = data.token;
            });
        }
        else {
            server.status = '0';
        }
    })
    .catch(error => {
        console.error('Failed to fetch server status:', error);
        server.status = '0';
    });
}


// Populate service list
function populateServers(servers) {
    const serverList = document.getElementById('serverList');
    serverList.innerHTML = ''; // Clear existing servers
    servers.forEach(server => {
        getServerStatus(server);
        const serverCard = document.createElement('div');
        serverCard.className = 'col-md-4';
        serverCard.innerHTML = `
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>${server.name}</h5>
                    <p>Status: <span class="text-${server.status == '1' ? 'success' : 'danger'}">${server.status == '1' ? 'Online' : 'Offline'}</span></p>
                    <p>Active Services: ${server.active_services}</p>
                    <button class="btn btn-primary btn-sm" onclick="loadServerDetails('${server.name}')">View Details</button>
                </div>
            </div>
        `;
        serverList.appendChild(serverCard);
    });
}

function populateServiceList(services) {
    const serviceList = document.getElementById('serviceList');
    serviceList.innerHTML = ''; // Clear existing services
    
    services.forEach(service => {
        const serviceCard = document.createElement('div');
        let active = false;
        if (service.status == '1') {
            active = true;
        }
        serviceCard.className = 'col-md-4';
        serviceCard.innerHTML = `
            <div class="card shadow-sm">
                <div class="card-body">
                ${service.protected ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-fill lock-icon" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/></svg>' : ''}
                    <span class="status-indicator ${active ? 'status-active' : 'status-inactive'}"></span>
                    <strong>${service.name}</strong>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary" data-service="${service.name}" data-action="restart" onclick="restartService('${service.name}')" ${service.protected ? 'disabled title="Service is protected"' : ''}>Restart</button>
                        <button class="btn btn-sm btn-secondary" data-service="${service.name}" data-action="status" onclick="statusService('${service.name}')">Status</button>
                        <button class="btn btn-sm btn-danger" data-service="${service.name}" data-action="stop" onclick="stopService('${service.name}')" ${service.protected ? 'disabled title="Service is protected"' : ''}>Stop</button>
                    </div>
                </div>
            </div>
        `;
        serviceList.appendChild(serviceCard);
    });
}

// Fetch and display logs
function populateLogFiles(logFiles) {
    const logFileList = document.getElementById('logFileList');
    logFileList.innerHTML = ''; // Clear existing logs
    Object.entries(logFiles).forEach(([key, value]) => {
        //console.log(`Key: ${key}`);
        const logPath = document.createElement('div');
        logPath.className = 'mt-4 mb-4';
        logPath.innerHTML = `<strong>PATH : ${key}</strong>`;
        logFileList.appendChild(logPath);
        value.forEach(file => {
            const logItem = document.createElement('div');
            
            logItem.className = 'col-md-4';
            logItem.innerHTML = `
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
                                <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5"/>
                                <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z"/>
                            </svg>
                            <strong>${file.name}</strong> (${(file.size / 1024).toFixed(2)} ${file.size >= 1048576 ? 'M' : 'K'})
                            <div class="mt-2">
                                <button class="btn btn-primary btn-sm" onclick="downloadLogFile('${key}/${file.name}')">Download</button>
                                <button class="btn btn-success btn-sm" data-log="${key}/${file.name}" data-action="view" onclick="viewLog('${key}/${file.name}')">View</button>
                            </div>
                        </div>
                    </div>
            `;
            
            logFileList.appendChild(logItem);
    
        });
    });
    
}

async function fetchServers() {
    const servers = await getServers();
    populateServers(servers);
}

async function fetchLogs() {
    const logFiles = await getLogFiles();
    populateLogFiles(logFiles);
}

// Initial data fetch
async function fetchServices() {
    const services = await getServices();
    populateServiceList(services);
}

async function fetchReports() {
    const reports = await getReports();
    reports.forEach(report => {
        fetchServices();
        if (report.success) {
            createAlert(report.message, 'success', false, false);
        }
        else {
            createAlert(report.message, 'error', false, false);
        }
    });
}

async function fetchInfo() {
    const info = await getInfo();
    console.log(info);
    if (info.version) {
        document.getElementById('version').textContent = info.version;
    }
    if (info.base_url) {
        base_url = info.base_url;
    }
    fetchServers();
    //fetchServices();
    //fetchLogs();
}

document.addEventListener('DOMContentLoaded', () => {

    let toggleScrollButton = document.getElementById('toggleScrollButton');
    // this tiggoel will disbale white-space: pre-wrap
    toggleScrollButton.addEventListener('click', () => {
        let logContent = document.getElementById('logContentPre');
        if (logContent.style.whiteSpace === 'pre') {
            logContent.style.whiteSpace = 'pre-wrap';
            toggleScrollButton.textContent = 'Enable Scroll';
        } else {
            logContent.style.whiteSpace = 'pre';
            toggleScrollButton.textContent = 'Disable Scroll';
        }
    });
    
    const codeElements = document.querySelectorAll('.code-animation');

    codeElements.forEach((element) => {
        // Remove the animation class after the animation ends
        element.addEventListener('animationend', () => {
            element.classList.remove('code-animation');
        });
    });
    
    document.getElementById('logoutButton').addEventListener('click', async () => {
        localStorage.removeItem('jwt');
        redirect();
    });

    const darkModeToggle = document.getElementById('darkModeToggle');

    // Check if dark mode was previously enabled
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        darkModeToggle.checked = true;
    }

    darkModeToggle.addEventListener('change', () => {
        if (darkModeToggle.checked) {
            document.body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'enabled');
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'disabled');
        }
    });

    // Add interval switch
    const intervalSwitch = document.getElementById('intervalSwitch');

    function startInterval() {
        intervalIds.intervalFetchServices = setInterval(fetchServices, 5000);
    }

    function stopInterval() {
        clearInterval(intervalIds.intervalFetchServices);
    }

    // Check if auto-refresh was previously enabled
    console.log(localStorage.getItem('autoRefresh'));
    if (localStorage.getItem('autoRefresh') === 'enabled') {
        intervalSwitch.checked = true;
        startInterval();
    }
    else if (intervalSwitch.checked) {
        intervalSwitch.checked = false;
        stopInterval();
    }
    else {
        intervalSwitch.checked = false;
    }

    intervalSwitch.addEventListener('change', function() {
        if (this.checked) {
            startInterval();
            localStorage.setItem('autoRefresh', 'enabled');
        } else {
            stopInterval();
            localStorage.setItem('autoRefresh', 'disabled');
        }
    });

    // Run initial fetch
    fetchInfo();
});


function redirectIfUnauthorized() {
    if (!localStorage.getItem('jwt')) {
        if (window.location.pathname !== '/') {
            redirect();
        }
    }
}
redirectIfUnauthorized();