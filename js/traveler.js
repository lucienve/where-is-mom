document.addEventListener('DOMContentLoaded', () => {
    const loginOverlay = document.getElementById('loginOverlay');
    const loginBtn = document.getElementById('loginBtn');
    const passwordInput = document.getElementById('travelerPassword');
    const errorMsg = document.getElementById('loginError');
    const dashboard = document.getElementById('travelerDashboard');

    const modeToggle = document.getElementById('modeToggle');
    const modeStatusMsg = document.getElementById('modeStatusMsg');
    const photoInput = document.getElementById('photoInput');
    const uploadBtn = document.getElementById('uploadBtn');
    const feedbackMsg = document.getElementById('uploadFeedback');

    // Check if authenticated
    function checkStatus() {
        return fetch('api.php?action=status')
            .then(res => res.json())
            .then(data => {
                if (data.authenticated && data.role === 'traveler') {
                    loginOverlay.classList.remove('active');
                    dashboard.style.display = 'block';
                    
                    // Sync toggle
                    modeToggle.checked = data.onShipMode;
                    updateToggleText(data.onShipMode);
                }
            });
    }

    checkStatus();

    loginBtn.addEventListener('click', () => {
        const password = passwordInput.value;
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('password', password);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.role === 'traveler') {
                checkStatus();
            } else {
                errorMsg.textContent = data.message || "Invalid password";
            }
        });
    });

    modeToggle.addEventListener('change', (e) => {
        const isShip = e.target.checked;
        const formData = new FormData();
        formData.append('action', 'set_mode');
        formData.append('mode', isShip ? '1' : '0');

        fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateToggleText(isShip);
                }
            });
    });

    function updateToggleText(isShip) {
        modeStatusMsg.textContent = isShip 
            ? "Currently tracking via Datadocked AIS." 
            : "Datadocked polling DISABLED. Photo locations only.";
    }

    photoInput.addEventListener('change', (e) => {
        uploadBtn.disabled = !e.target.files.length;
    });

    uploadBtn.addEventListener('click', () => {
        const file = photoInput.files[0];
        if (!file) return;

        feedbackMsg.textContent = "Uploading...";
        feedbackMsg.className = 'feedback-msg';
        uploadBtn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('photo', file);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.gps_missing) {
                    feedbackMsg.textContent = `Warning: EXIF GPS missing. Using fallback lat: ${data.fallback_lat}, lng: ${data.fallback_lng}`;
                    feedbackMsg.className = 'feedback-msg warning';
                } else {
                    feedbackMsg.textContent = "Photo uploaded successfully!";
                    feedbackMsg.className = 'feedback-msg success';
                }
                photoInput.value = ''; // Clear
            } else {
                feedbackMsg.textContent = "Error: " + (data.error || "Upload failed");
                feedbackMsg.className = 'feedback-msg error';
            }
        })
        .catch(err => {
            feedbackMsg.textContent = "Network error during upload.";
            feedbackMsg.className = 'feedback-msg error';
        })
        .finally(() => {
            uploadBtn.disabled = !photoInput.files.length;
        });
    });
});
