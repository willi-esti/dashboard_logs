
const API_BASE_URL = '/api';

let l;
// Fetch user
async function authenticateUser(username, password) {
    try {
        const response = await fetch(`${API_BASE_URL}/authenticate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Basic ' + btoa(username + ':' + password)
            }
        });
        return await response;
    } catch (error) {
        console.error('Error registering user:', error);
        return { success: false, error: error.message };
    }
}


document.getElementById('registrationForm').addEventListener('submit', async (event) => {
    event.preventDefault();

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    // Validate input
    if (username.length < 3 || password.length < 6) {
        alert('Username must be at least 3 characters and password at least 6 characters.');
        return;
    }

    // Send data to the server
    const response = await authenticateUser(username, password);
    console.log("response", response);
    if (response.ok) {
        // rediect to dashboard.php
        console.log('Registration successful!');
        // set cookie token
        // like this localStorage.getItem('jwt'); 
        if (response.headers.has('Authorization')) {
            const newToken = response.headers.get('Authorization').split(' ')[1];
            localStorage.setItem('jwt', newToken);
        }
        else
        {
            throw new Error('No token found in response!');
        }
        window.location.href = '/dashboard.html';
    } else {
        createAlert(response.error, 'danger');
        console.log(`Error: ${response.error}`);
    }
});
