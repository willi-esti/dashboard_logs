// make a fetch functon
async function fetchAPI(url, method, body = null, finallyCallback, ...finallyArgs) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            }
        };
        if (body) {
            options.body = JSON.stringify(body);
        }
        const response = await fetch(url, options);
        updateToken(response);
        if (!response.ok) {
            const data = await response.json();
            if (data.error === 'Unauthorized') {
                localStorage.removeItem('jwt');
                Object.keys(intervalIds).forEach(key => {
                    clearInterval(intervalIds[key]);
                });
                //redirect();
            }
            createAlert(data.message, 'error', false);
            throw new Error(data.message);
        }
        return await response.json();
    } catch (error) {
        console.error('Error fetching data:', error);
        return [];
    } finally {
        if (finallyCallback) {
            finallyCallback(...finallyArgs);
        }
    }
}

async function getInfo() {
    return await fetchAPI(`${API_BASE_URL}/info`, 'GET');
}

// Fetch services
async function getServices() {
    return await fetchAPI(`${API_BASE_URL}/services`, 'GET');
}

// Restart a service
async function restartService(serviceName) {
    loadingAnimation(true, 'service', serviceName, 'restart', 'Restart');
    response = await fetchAPI(`${API_BASE_URL}/services`, 'POST', { action: 'restart', service: serviceName }, loadingAnimation, false, 'service', serviceName, 'restart', 'Restart');
    if (response.status === 0) {
        fetchServices();
        createAlert('Service restarted successfully!', 'success', 5000, false);
    }
    else if (response.status === 2) {
        createAlert(response.message, 'info', false, false);
    }
    else {
        createAlert('Error restarting service.', 'error', 5000, false);
    }
}

// Status of a service
async function statusService(serviceName) {
    loadingAnimation(true, 'service', serviceName, 'status', 'Status');
    response = await fetchAPI(`${API_BASE_URL}/services`, 'POST', { action: 'status', service: serviceName }, loadingAnimation, false, 'service', serviceName, 'status', 'Status');
    // making a modal to show the status
    const modal = document.getElementById('statusModal');
    const modalBody = document.getElementById('statusModalContent');
    modalBody.style.whiteSpace = 'pre';
    modalBody.innerHTML = '';
    response.content.forEach(element => {
        modalBody.innerHTML += `${element}<br>`;
    });
    const statusModal = new bootstrap.Modal(modal);
    statusModal.show();
}
// Stop a service
async function stopService(serviceName) {
    loadingAnimation(true, 'service', serviceName, 'stop', 'Stop');
    fetchAPI(`${API_BASE_URL}/services`, 'POST', { action: 'stop', service: serviceName }, loadingAnimation, false, 'service', serviceName, 'stop', 'Stop');
}

// Fetch log files
async function getLogFiles() {
    return await fetchAPI(`${API_BASE_URL}/logs`, 'GET');
}

async function getReports() {
    return await fetchAPI(`${API_BASE_URL}/reports`, 'GET');
}

async function getReportsDebug() {
    return await fetchAPI(`${API_BASE_URL}/reports?debug=true`, 'GET');
}

// Download log file
async function downloadLogFile(filePath) {
    const response = await fetch(`${API_BASE_URL}/logs/download?file=${filePath}`, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    if (!response.ok) {
        console.error('Failed to download file:', response.statusText);
        return;
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    const splitPath = filePath.split('/');
    const fileName = splitPath[splitPath.length - 1];
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

