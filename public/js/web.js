// Web.js: Handles DOM interactions and updates dynamically

const API_BASE_URL = 'api';
let base_url = '/';

function updateToken(response)
{
    if (response.headers.has('Authorization')) {
        const newToken = response.headers.get('Authorization').split(' ')[1];
        console.log("newToken", newToken);
        localStorage.setItem('jwt', newToken);
    }
}

function redirect() {
    console.log("redirect")
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

