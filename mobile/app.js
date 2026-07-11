document.addEventListener('DOMContentLoaded', async () => {
  const statusEl = document.getElementById('statusValue');
  const healthTextEl = document.getElementById('healthText');
  const loadingText = 'Checking...';

  if (statusEl) statusEl.textContent = loadingText;
  if (healthTextEl) healthTextEl.textContent = 'Connecting to the Carbnb API...';

  try {
    const response = await fetch('../api/health.php');
    const data = await response.json();

    if (statusEl) {
      statusEl.textContent = data.success ? 'Online' : 'Offline';
      statusEl.style.background = data.success ? '#243b2b' : '#4b1f23';
      statusEl.style.color = data.success ? '#8eea96' : '#ffb3b3';
    }

    if (healthTextEl) {
      healthTextEl.textContent = data.success
        ? `${data.service} is responding normally.`
        : 'The API is unavailable right now.';
    }
  } catch (error) {
    if (statusEl) {
      statusEl.textContent = 'Offline';
      statusEl.style.background = '#4b1f23';
      statusEl.style.color = '#ffb3b3';
    }
    if (healthTextEl) {
      healthTextEl.textContent = 'Unable to reach the API. Make sure the PHP server is running.';
    }
  }
});
