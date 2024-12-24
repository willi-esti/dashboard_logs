
// Fetching.js: Handles API requests

const API_BASE_URL = '/api';
let l = null;
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
            createAlert(data.message, 'error');
            throw new Error(data.message);
        }
        updateToken(response);

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching services:', error.message);
    }
}

// Add a new service
async function addService(serviceName) {
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: serviceName })
        });
        updateToken(response);
        return await response.json();
    } catch (error) {
        console.error('Error adding service:', error);
        return { success: false, error: error.message };
    }
}

// Restart a service
async function restartService(serviceName) {
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'restart', service: serviceName })
        });
        updateToken(response);
        const result = await response.json();
        alert(result.status === 0 ? 'Service restarted successfully!' : 'Error restarting service.');
        fetchServices();
    } catch (error) {
        console.error('Error restarting service:', error);
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

// Remove a service
async function removeService(serviceName) {
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ service: serviceName })
        });
        updateToken(response);
        const result = await response.json();
        alert(result.status === 0 ? 'Service removed successfully!' : 'Error removing service.');
        fetchServices();
    } catch (error) {
        console.error('Error removing service:', error);
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
        createAlert('Error fetching log content.', 'error');
        redirect();
        return { content: 'Error fetching log content.' };
    }
}
