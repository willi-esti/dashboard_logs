function showLogContent(file) {
    logContent.hidden = false;
    logContent.textContent = ""; // Clear previous content

    function fetchLog() {
        fetch(`stream-log.php?file=${file}`)
            .then(response => response.text())
            .then(data => {
                logContent.textContent += data; // Append new lines
                logContent.scrollTop = logContent.scrollHeight; // Auto-scroll to the bottom
                fetchLog(); // Continuously fetch updates
            })
            .catch(error => {
                logContent.textContent += "\n[Error fetching log content]";
            });
    }

    fetchLog();
}
