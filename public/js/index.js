
const API_BASE_URL = '/api';

// Fetch user
async function authenticateUser(username, password) {
    try {
        const response = await fetch(`${API_BASE_URL}/authenticate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Basic ' + btoa(username + ':' + password)
            },
            body: JSON.stringify({ username, password })
        });
        return await response.json();
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
    console.log("response", response.success);
    if (response.success) {
        // rediect to dashboard.php
        console.log('Registration successful!');
        // set cookie token
        // like this localStorage.getItem('jwt'); 
        localStorage.setItem('jwt', response.token);
        //document.cookie = `token=${response.token}; path=/`;

        window.location.href = '/dashboard.html';
    } else {
        console.log(`Error: ${response.error}`);
    }
});
