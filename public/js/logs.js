const token = localStorage.getItem('jwt'); // Retrieve the JWT token from local storage
const socket = new WebSocket(`ws://localhost:8080?token=${token}`);

socket.onopen = function(event) {
    console.log('WebSocket is connected.');
};

socket.onmessage = function(event) {
    console.log('Received message:', event.data);
};

socket.onclose = function(event) {
    console.log('WebSocket is closed.');
};

socket.onerror = function(error) {
    console.error('WebSocket error:', error);
};
