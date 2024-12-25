let token = localStorage.getItem('jwt'); // Retrieve the JWT token from local storage
//let logFile = 'errors.log'; // Specify the log file to monitor
let socket;
let reconnectInterval = 5000; // Time in milliseconds to wait before attempting to reconnect

function connectWebSocket(logFile) {
    socket = new WebSocket(`ws://ca.it-techs.fr:8080?token=${token}&logFile=${encodeURIComponent(logFile)}`);

    socket.onopen = function(event) {
        console.log('WebSocket is connected.');
        // Send a message to trigger follow action
        socket.send(JSON.stringify({ action: 'follow', lastLines: 10 }));
    };

    socket.onmessage = function(event) {
        logData = JSON.parse(event.data);
        console.log(logData);
        if (logData.follow) {
            // Append new log lines to the log content
            const logContent = document.getElementById('logContentPre');
            logData.follow.forEach(element => {
                //data-line-number="${element.line}"
                logContent.innerHTML += `<code data-line-number="${element.line}">${element.content}</code></>`;
            });
        }
        else if (logData.getLogs){
            const logContent = document.getElementById('logContentPre');
            logData.getLogs.forEach(element => {
                //data-line-number="${element.line}"
                // adding the data above
                //logContent.innerHTML += `<pre><code data-line-number="${element.line}">${element.content}</code></pre>`;
                logContent.innerHTML += `<code data-line-number="${element.line}">${element.content}</code></>`;
            });
        }
        /*async function viewLog(fileName) {
            const response = await fetchLogContent(fileName);
            const logContent = document.getElementById('logContent');
            
            // wrap each line of the content in a <code>
            logContent.innerHTML = `<pre>${response.content.split('\n').map(line => `<code>${line}</code>`).join('\n') || 'Error fetching log content.'}</pre>`;
        }*/
        
                
        console.log('Received message:', event.data);
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
    setTimeout(() => {
        console.log('Reconnecting...');
        connectWebSocket();
    }, reconnectInterval);
}

// Initial connection
async function viewLog(logFile) {
    connectWebSocket(logFile);
}