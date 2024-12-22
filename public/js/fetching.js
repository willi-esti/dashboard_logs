
// Fetching.js: Handles API requests

const API_BASE_URL = '/api';

// Fetch services
async function getServices() {
    const response = await fetch(`${API_BASE_URL}/services`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + document.cookie.split('=')[1]
        }
    })
    .then((response) => {
        if (response.ok) {
            console.log(response.json());
            return response.json();
        }
        console.log(response.statusText)
        throw new Error(response.statusText);
    })
    .catch((error) => {
        redirect(error);
        console.log(error)
    });
}

// Add a new service
async function addService(serviceName) {
    try {
        const response = await fetch(`${API_BASE_URL}/services`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: serviceName })
        });
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
                'Authorization': 'Bearer ' + document.cookie.split('=')[1]
            }
        });
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
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file: fileName })
        });
        return await response.json();
    } catch (error) {
        console.error('Error fetching log content:', error);
        redirect();
        return { content: 'Error fetching log content.' };
    }
}
