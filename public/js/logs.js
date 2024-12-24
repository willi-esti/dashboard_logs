let token = localStorage.getItem('jwt'); // Retrieve the JWT token from local storage
let logFile = '/var/www/html/server-dashboard/logs/errors.log'; // Specify the log file to monitor
let socket;
let reconnectInterval = 5000; // Time in milliseconds to wait before attempting to reconnect

function connectWebSocket() {
    socket = new WebSocket(`ws://ca.it-techs.fr:8080?token=${token}&logFile=${encodeURIComponent(logFile)}`);

    socket.onopen = function(event) {
        console.log('WebSocket is connected.');
    };

    socket.onmessage = function(event) {
        console.log('Received message:', event.data);
    };

    socket.onclose = function(event) {
        console.log('WebSocket is closed.');
        console.log('Close event:', event);
        createAlert('WebSocket connection closed. Attempting to reconnect...', 'error', false);
        attemptReconnect();
    };

    socket.onerror = function(error) {
        console.error('WebSocket error:', error);
        socket.close(); // Close the socket on error to trigger the onclose event
    };
}

function attemptReconnect() {
    console.log(`Attempting to reconnect in ${reconnectInterval / 1000} seconds...`);
    setTimeout(() => {
        console.log('Reconnecting...');
        connectWebSocket();
    }, reconnectInterval);
}

// Initial connection
connectWebSocket();