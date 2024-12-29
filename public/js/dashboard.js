document.addEventListener('DOMContentLoaded', () => {
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
                                <button class="btn btn-success btn-sm" data-log="${file}" data-action="view" onclick="viewLog('${file}')">View</button>
                            </div>
                        </div>
                    </div>
            `;
            
            logFileList.appendChild(logItem);

        });
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

    let toggleScrollButton = document.getElementById('toggleScrollButton');
    // this tiggoel will disbale white-space: pre-wrap
    toggleScrollButton.addEventListener('click', () => {
        //console.log(logContent.style.whiteSpace);
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
            console.log('Animation ended');
            element.classList.remove('code-animation');
        });
    });
    
    document.getElementById('logoutButton').addEventListener('click', async () => {
        localStorage.removeItem('jwt');
        window.location.href = '/';
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
    let intervalId;

    function startInterval() {
        intervalId = setInterval(fetchServices, 5000);
    }

    function stopInterval() {
        clearInterval(intervalId);
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

    // Check if auto-refresh was previously enabled
    if (localStorage.getItem('autoRefresh') === 'enabled') {
        intervalSwitch.checked = true;
        startInterval();
    } else {
        intervalSwitch.checked = false;
    }

    // Run initial fetch
    fetchServices();
    fetchLogs();
    if (intervalSwitch.checked) {
        startInterval(); // Start the interval if enabled
    }
});
