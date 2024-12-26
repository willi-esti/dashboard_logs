// Web.js: Handles DOM interactions and updates dynamically

const API_BASE_URL = '/api';

function updateToken(response)
{
    if (response.headers.has('Authorization')) {
        const newToken = response.headers.get('Authorization').split(' ')[1];
        console.log("newToken", newToken);
        localStorage.setItem('jwt', newToken);
    }
}

function redirect(error) {
    console.log("redirect");
    createAlert(error, 'error', false);
}

function createAlert(message, type = 'success', timer = 5000, goback = true) {
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
    alertDiv.style.position = 'fixed';
    alertDiv.style.bottom = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        <strong>${alertName}:</strong> ${message}
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="alert" onclick="dismissAlert(this)">OK</button>
    `;
    if (goback === true) {
        alertDiv.innerHTML += `
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="alert" onclick="dismissAlert(this)">OK</button>
        `;
    }
    document.body.appendChild(alertDiv);

    if (timer !== false) {
        setTimeout(() => {
            dismissAlert(alertDiv.querySelector('[data-dismiss="alert"]'));
        }, timer);
    }
}
//createAlert('Welcome to the dashboard!', 'error');

function dismissAlert(button) {
    const alertDiv = button.parentElement;
    alertDiv.classList.remove('show');
    alertDiv.classList.add('hide');
    setTimeout(() => {
        document.body.removeChild(alertDiv);
    }, 500); // Wait for the hide transition to complete
}

function redirectIfUnauthorized() {
    // with localStorage.getItem('jwt')
    if (!localStorage.getItem('jwt')) {
        if (window.location.pathname !== '/') {
            //console.log(window.location.pathname);
            redirect('No token found');
        }
    }
}
redirectIfUnauthorized();
