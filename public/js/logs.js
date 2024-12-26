let token = localStorage.getItem('jwt'); // Retrieve the JWT token from local storage
//let logFile = 'errors.log'; // Specify the log file to monitor
let socket;
let logFile = '';
let reconnectInterval = 5000; // Time in milliseconds to wait before attempting to reconnect

function connectWebSocket(logFile) {
    try {
        socket.close();
    }
    catch (error) {
        console.log('No existing WebSocket connection to close.');
    }
    domain = window.location.hostname;
    socket = new WebSocket(`wss://${domain}/api/logs/stream?token=${token}&logFile=${encodeURIComponent(logFile)}`);

    socket.onopen = function(event) {
        console.log('WebSocket is connected.');
        // Send a message to trigger follow action
        let lineCountInput = document.getElementById('lineCountInput');
        socket.send(JSON.stringify({ action: 'follow', lastLines: lineCountInput.value }));
        const logContent = document.getElementById('logContentPre');
        logContent.innerHTML = '';
    };

    socket.onmessage = function(event) {
        //console.log('Received message:', event.data);
        console.log('Received message:');
        let logData;
        try {
            logData = JSON.parse(event.data);
        } catch (error) {
            console.log('Failed to parse JSON:', error);
            return;
        }
        //console.log(logData);

        if (logData.follow) {
            const logContent = document.getElementById('logContentPre');
            logData.follow.forEach(element => {
                logContent.innerHTML += `<code class="code-animation" data-line-number="${element.line}">${element.content}</code></>`;
            });
            removingAnimation();
        }
        else if (logData.getLogs){
            const logContent = document.getElementById('logContentPre');
            logData.getLogs.forEach(element => {
                logContent.innerHTML += `<code class="code-animation" data-line-number="${element.line}">${element.content}</code></>`;
            });
            removingAnimation();
        }
        else if (logData.error) {
            createAlert(`[WebSocket] Error: ${logData.error}`, 'error', 5000);
            if (logData.error === 'Invalid token' || logData.error === 'Unauthorized') {
                redirect();
            }
        }
        else {
            console.log('Unknown message:', logData);
        }
        
        let followToggle = document.getElementById('followToggle');
        if (followToggle.checked) {
            const logContent = document.getElementById('logContent');
            logContent.scrollTop = logContent.scrollHeight;
        }
    };

    socket.onclose = function(event) {
        console.log('WebSocket is closed.', event);
        if (event.wasClean) {
            console.log(`Connection closed cleanly, code=${event.code} reason=${event.reason}`);
        }
        else {
            attemptReconnect();
        }
    };

    socket.onerror = function(error) {
        console.error('WebSocket error:', error);
        socket.close(); // Close the socket on error to trigger the onclose event
    };
}

function attemptReconnect() {
    console.log(`Attempting to reconnect in ${reconnectInterval / 1000} seconds...`);
    createAlert('[WebSocket] Connection to the server was lost. Attempting to reconnect...', 'error', 5000);
    setTimeout(() => {
        console.log('Reconnecting...');
        connectWebSocket(logFile);
    }, reconnectInterval);
}

function removingAnimation() {
    const codeElements = document.querySelectorAll('.code-animation');
    codeElements.forEach((element) => {
        setTimeout(() => {
            element.classList.remove('code-animation');
        }, 2000);
    });
}

// Initial connection
async function viewLog(logFile) {
    connectWebSocket(logFile);
}


async function downloadLogFile(fileName) {
    const response = await fetch(`${API_BASE_URL}/logs/download?file=${fileName}`, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    if (!response.ok) {
        console.error('Failed to download file:', response.statusText);
        return;
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}