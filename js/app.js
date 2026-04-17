/* global initMapWithKey */

document.addEventListener('DOMContentLoaded', () => {
  const loginOverlay = document.getElementById('loginOverlay');
  const loginBtn = document.getElementById('loginBtn');
  const passwordInput = document.getElementById('viewerPassword');
  const errorMsg = document.getElementById('loginError');

  // Check if already authenticated by fetching status
  fetch('api.php?action=status')
      .then((res) => res.json())
      .then((data) => {
        if (data.authenticated && data.role === 'viewer') {
          loginOverlay.classList.remove('active');
          if (typeof initMapWithKey === 'function') {
            initMapWithKey(data.mapsApiKey);
          }
        }
      });

  loginBtn.addEventListener('click', () => {
    const password = passwordInput.value;
    const formData = new FormData();
    formData.append('action', 'login');
    formData.append('password', password);

    fetch('api.php', {
      method: 'POST',
      body: formData,
    })
        .then((res) => res.json())
        .then((data) => {
          if (data.success && data.role === 'viewer') {
            loginOverlay.classList.remove('active');

            // Fetch status again to get map key
            fetch('api.php?action=status')
                .then((res) => res.json())
                .then((state) => {
                  if (typeof initMapWithKey === 'function') {
                    initMapWithKey(state.mapsApiKey);
                  }
                });
          } else {
            errorMsg.textContent = data.message || 'Invalid password';
          }
        })
        .catch(() => {
          errorMsg.textContent = 'Network error occurred.';
        });
  });
});

// Lightbox logic
const lightbox = document.getElementById('lightbox');
const closeBtn = document.getElementById('closeLightbox');
if (closeBtn) {
  closeBtn.addEventListener('click', () => lightbox.classList.remove('active'));
}
