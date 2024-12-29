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
            createAlert('Service restarted successfully!', 'success', false);
        }
        else {
            createAlert('Error restarting service.', 'error', false);
        }
        console.log(result);
        
    } catch (error) {
        console.error('Error restarting service:', error);
    }
}

// Status of a service
async function statusService(serviceName) {
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
        console.log(result.status);
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
}

// Stop a service
async function stopService(serviceName) {
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'stop', service: serviceName })
        });
        updateToken(response);
        const result = await response.json();
        alert(result.status === 0 ? 'Service stopped successfully!' : 'Error stopping service.');
        fetchServices();
    } catch (error) {
        console.error('Error stopping service:', error);
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
        //const response = await fetch(`${API_BASE_URL}/logs`, { method: 'GET' });
        return await response.json();
    } catch (error) {
        console.error('Error fetching log files:', error);
        return [];
    }
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
