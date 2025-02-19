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
        
        if (response.ok) {
            if (response.headers.has('Authorization')) {
                const newToken = response.headers.get('Authorization').split(' ')[1];
                localStorage.setItem('jwt', newToken);
            }
            else
            {
                createAlert('No token found in response!', 'danger', false);
                throw new Error('No token found in response!');
            }
            window.location.href = 'dashboard.html';
        } else {
            error = await response.json();
            createAlert(error.message, 'danger', 5000, false);
            //console.log(`Error: ${error.message}`);
        }
        return await response;
    } catch (error) {
        //createAlert(error.message, 'danger', false, false);
        console.error('Error registering user:', error);
        return { success: false, error: error.message };
    }
}


document.getElementById('registrationForm').addEventListener('submit', async (event) => {
    event.preventDefault();

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    // Send data to the server
    const response = await authenticateUser(username, password);
});
