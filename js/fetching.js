
addServiceForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const serviceName = document.getElementById('serviceName').value;

    if (serviceName) {
        fetch('php/add_service.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `name=${encodeURIComponent(serviceName)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                loadServices();
                document.getElementById('serviceName').value = '';
            } else {
                alert('Error adding service.');
            }
        });
    }
});


function loadServices() {
    fetch('php/fetch_services.php')
        .then(response => response.json())
        .then(services => {
            serviceList.innerHTML = '';
            services.forEach(service => {
                const serviceCard = document.createElement('div');
                serviceCard.className = 'col-md-4';
                serviceCard.innerHTML = `
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <span class="status-indicator status-inactive"></span>
                            <strong>${service.name}</strong>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-primary">Restart</button>
                                <button class="btn btn-sm btn-secondary">Stop</button>
                                <button class="btn btn-sm btn-danger" onclick="removeService(${service.id})">Remove</button>
                            </div>
                        </div>
                    </div>
                `;
                serviceList.appendChild(serviceCard);
            });
        });
}
loadServices();


function removeService(id) {
    fetch('php/remove_service.php', {
        method: 'DELETE',
        body: `id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            loadServices();
        } else {
            alert('Error removing service.');
        }
    });
}


function showLogContent(file) {
    logContent.hidden = false;
    logContent.textContent = ""; // Clear previous content

    function fetchLog() {
        fetch(`php/stream-log.php?file=${file}`)
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
