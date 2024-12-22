// Web.js: Handles DOM interactions and updates dynamically

function redirect(error) {
    console.log("redirect");
    createAlert(error, 'error');
}

function createAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    console.log("createAlert");
    alertType = type;
    if (type === 'error') {
        alertName = 'Error';
        alertType = 'danger';
    }
    else if (type === 'success') {
        alertName = 'Success';
    }
    else {
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
        <button type="button" class="btn btn-primary btn-sm" onclick="window.location.href='/'">Go back to login</button>
    `;
    document.body.appendChild(alertDiv);
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
    if (!document.cookie.includes('token')) {
        redirect('No token found');
    }
}
redirectIfUnauthorized();
