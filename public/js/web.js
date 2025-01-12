// Web.js: Handles DOM interactions and updates dynamically

/** Global variables */
// API
const API_BASE_URL = 'api';
let base_url = '/';

// Intervals for fetching data
let intervalIds = {};

// Websocket
let token = localStorage.getItem('jwt'); // Retrieve the JWT token from local storage
let socket;
let logFile = '';
let reconnectInterval = 5000; // Time in milliseconds to wait before attempting to reconnect


function updateToken(response)
{
    if (response.headers.has('Authorization')) {
        const newToken = response.headers.get('Authorization').split(' ')[1];
        console.log('Token refreshed');
        localStorage.setItem('jwt', newToken);
    }
}

function redirect() {
    window.location.href = 'index.html'
    //createAlert(error, 'error', false);
}

function loadingAnimation(enable=true, dataType, data, action, text) {
    const button = document.querySelector(`button[data-${dataType}="${data}"][data-action="${action}"]`);
    if (enable) {
        button.disabled = true;
        button.innerHTML = `<span class="spinner-grow spinner-grow-sm" aria-hidden="true"></span> ${text}`;
    }
    else {
        button.disabled = false;
        button.innerHTML = text;
    }
}

function createAlert(message, type = 'success', timer = 5000, goback = true) {
    const alertContainer = document.getElementById('alert-container');
    const alertDiv = document.createElement('div');
    
    let alertType = type;
    let alertName;
    if (type === 'error') {
        alertName = 'Error';
        alertType = 'danger';
    } else if (type === 'success') {
        alertName = 'Success';
    } else {
        alertName = 'Info';
    }
    alertDiv.className = `alert alert-${alertType} alert-dismissible fade show`;
    /*alertDiv.style.position = 'fixed';
    alertDiv.style.bottom = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';*/
    alertDiv.innerHTML = `
        <strong>${alertName}:</strong> ${message}
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="alert" onclick="dismissAlert(this.parentElement)">OK</button>
    `;
    if (goback === true) {
        alertDiv.innerHTML += `
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="alert" onclick="window.location.href = 'index.html'">Go Back</button>
        `;
    }
    alertContainer.appendChild(alertDiv);
    //document.body.appendChild(alertDiv);

    if (timer !== false) {
        setTimeout(() => {
            dismissAlert(alertDiv);
            //alertContainer.removeChild(alertDiv);
        }, timer);
    }
}
//createAlert('Welcome to the dashboard!', 'error');

function dismissAlert(button) {
    const alertDiv = button;
    alertDiv.classList.remove('show');
    alertDiv.classList.add('hide');
    setTimeout(() => {
        document.getElementById('alert-container').removeChild(alertDiv);
    }, 500); // Wait for the hide transition to complete
}

function clearIntervals() {
    Object.keys(intervalIds).forEach(key => {
        clearInterval(intervalIds[key]);
    });
}

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
        // if error 500
        if (response.status === 500) {
            clearIntervals();
            createAlert('Internal server error. Check the logs for more information.', 'error', false);
            throw new Error('Internal server error. Check the logs for more information.');
        }
        if (response.status === 401) {
            localStorage.removeItem('jwt');
            clearIntervals();
            if (url !== `${API_BASE_URL}/authenticate`) {
                createAlert('')
            }
            createAlert('Unauthorized. Please login.', 'error', false);
            throw new Error('Unauthorized. Please login.');
        }
        if (!response.ok) {
            const data = await response.json();
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


