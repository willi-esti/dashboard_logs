// Update the last updated timestamp
document.getElementById('lastUpdate').innerText = new Date().toLocaleString();

// Add new service functionality
const serviceList = document.getElementById('serviceList');
const addServiceForm = document.getElementById('addServiceForm');


// Populate log files dynamically (mockup example)
const logFiles = ["server.log", "errors.log", "access.log"];
const logFileList = document.getElementById('logFileList');
const logContent = document.getElementById('logContent');

logFiles.forEach(file => {
    const listItem = document.createElement('li');
    listItem.innerHTML = `
        <button class="btn btn-link" onclick="showLogContent('${file}')">${file}</button>
        <button class="btn btn-sm btn-secondary" onclick="downloadLog('${file}')">Download</button>
    `;
    logFileList.appendChild(listItem);
});

function showLogContent(file) {
    // Mockup content (replace with actual fetch logic)
    const mockContent = {
        "server.log": "Server started at 12:00 PM\nListening on port 8080...",
        "errors.log": "[Error] Failed to connect to database\n[Error] Unauthorized access attempt detected",
        "access.log": "192.168.0.1 - - [20/Dec/2024:12:00:00 +0000] \"GET /index.html HTTP/1.1\" 200 1024"
    };

    logContent.hidden = false;
    logContent.textContent = mockContent[file] || "Log content not found.";
}

function downloadLog(file) {
    // Replace with actual download logic
    alert(`Downloading: ${file}`);
}

// Remove service functionality
document.querySelectorAll('.remove-service').forEach(button => {
    button.addEventListener('click', (e) => {
        e.target.closest('.col-md-4').remove();
    });
});
