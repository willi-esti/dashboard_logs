// Fetch services
async function getServices() {
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            }
        });

        if (!response.ok) {
            const data = await response.json();
            createAlert(data.message, 'error', false);
            throw new Error(data.message);
        }
        updateToken(response);

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching services:', error.message);
    }
}

// Restart a service
async function restartService(serviceName) {
    loadingAnimation(true, 'service', serviceName, 'restart', 'Restart');
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            },
            body: JSON.stringify({ action: 'restart', service: serviceName })
        });
        updateToken(response);
        const result = await response.json();
        if (result.status === 0) {
            fetchServices();
            createAlert('Service restarted successfully!', 'success', 5000, false);
        }
        else if (result.status === 2) {
            createAlert(result.message, 'info', false, false);
        }
        else {
            createAlert('Error restarting service.', 'error', 5000, false);
        }
    } catch (error) {
        console.error('Error restarting service:', error);
    } finally {
        loadingAnimation(false, 'service', serviceName, 'restart', 'Restart');
    }
}

// Status of a service
async function statusService(serviceName) {
    loadingAnimation(true, 'service', serviceName, 'status', 'Status');
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            },
            body: JSON.stringify({ action: 'status', service: serviceName })
        });
        updateToken(response);
        const result = await response.json();
        // making a modal to show the status
        const modal = document.getElementById('statusModal');
        const modalBody = document.getElementById('statusModalContent');
        modalBody.style.whiteSpace = 'pre';
        modalBody.innerHTML = '';
        result.content.forEach(element => {
            modalBody.innerHTML += `${element}<br>`;
        });
        const statusModal = new bootstrap.Modal(modal);
        statusModal.show();
        //fetchServices();
    } catch (error) {
        console.error('Error checking service status:', error);
    }
    finally {
        loadingAnimation(false, 'service', serviceName, 'status', 'Status');
    }
}

// Stop a service
async function stopService(serviceName) {
    loadingAnimation(true, 'service', serviceName, 'stop', 'Stop');
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            },
            body: JSON.stringify({ action: 'stop', service: serviceName })
        });
        updateToken(response);
        const result = await response.json();
        if (result.status === 0) {
            fetchServices();
            createAlert('Service stopped successfully!', 'success', 5000, false);
        }
        else if (result.status === 2) {
            createAlert(result.message, 'info', false, false);
        }
        else {
            createAlert('Error stopping service.', 'error', 5000, false);
        }
    } catch (error) {
        console.error('Error stopping service:', error);
    } finally {
        loadingAnimation(false, 'service', serviceName, 'stop', 'Stop');
    }
}

// Fetch log files
async function getLogFiles() {
    try {
        const response = await fetch(`${API_BASE_URL}/logs`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            }
        });
        updateToken(response);
        if (!response.ok) {
            const data = await response.json();
            createAlert(data.message, 'error', false);
            throw new Error(data.message);
        }
        //const response = await fetch(`${API_BASE_URL}/logs`, { method: 'GET' });
        return await response.json();
    } catch (error) {
        console.error('Error fetching log files:', error);
        return [];
    }
}

async function getReports() {
    try {
        const response = await fetch(`${API_BASE_URL}/reports`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            }
        });
        updateToken(response);
        return await response.json();
    } catch (error) {
        console.error('Error fetching report files:', error);
        return [];
    }
}

async function getReportsDebug() {
    try {
        const response = await fetch(`${API_BASE_URL}/reports?debug=true`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            }
        });
        updateToken(response);
        // parse the json
        console.log(await response.json());
        //return await response.json();
    }
    catch (error) {
        console.error('Error fetching report files:', error);
        return [];
    }
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

// Fetch log content
async function fetchLogContent(fileName) {
    try {
        const response = await fetch(`${API_BASE_URL}/logs`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            },
            body: JSON.stringify({ file: fileName })
        });
        updateToken(response);
        return await response.json();
    } catch (error) {
        //console.error('Error fetching log content:', error);
        createAlert('Error fetching log content.', 'error', false);
        return { content: 'Error fetching log content.' };
    }
}

async function getInfo() {
    try {
        const response = await fetch(`${API_BASE_URL}/info`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('jwt')
            }
        });
        if (!response.ok) {
            const data = await response.json();
            createAlert(data.message, 'error', false);
            throw new Error(data.message);
        }
        updateToken(response);
        return await response.json();
    } catch (error) {
        console.error('Error fetching info:', error);
        return [];
    }
}
