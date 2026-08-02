// PWA functionality for Meetup Chat

// Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('Service Worker registered successfully:', registration.scope);

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New service worker available, show update notification
                            showUpdateNotification();
                        }
                    });
                });
            })
            .catch(error => {
                console.error('Service Worker registration failed:', error);
            });

        // Handle service worker updates
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });
    });
}

// PWA Install Prompt
let deferredPrompt;
const installButton = document.getElementById('pwa-install-btn');

window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent the mini-infobar from appearing on mobile
    e.preventDefault();
    // Stash the event so it can be triggered later
    deferredPrompt = e;
    // Show install button if it exists
    if (installButton) {
        installButton.style.display = 'block';
    }
});

// Handle install button click
if (installButton) {
    installButton.addEventListener('click', async () => {
        if (!deferredPrompt) {
            return;
        }
        // Show the install prompt
        deferredPrompt.prompt();
        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`User response to the install prompt: ${outcome}`);
        // Clear the deferredPrompt
        deferredPrompt = null;
        // Hide the install button
        installButton.style.display = 'none';
    });
}

// Detect if app is installed
window.addEventListener('appinstalled', () => {
    console.log('PWA was installed');
    deferredPrompt = null;
    if (installButton) {
        installButton.style.display = 'none';
    }
});

// Online/Offline Status Detection
function updateOnlineStatus() {
    const statusIndicator = document.getElementById('online-status');
    if (statusIndicator) {
        if (navigator.onLine) {
            statusIndicator.classList.remove('offline');
            statusIndicator.classList.add('online');
            statusIndicator.textContent = 'Online';
        } else {
            statusIndicator.classList.remove('online');
            statusIndicator.classList.add('offline');
            statusIndicator.textContent = 'Offline';
        }
    }
}

window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);

// Show update notification
function showUpdateNotification() {
    const notification = document.createElement('div');
    notification.id = 'update-notification';
    notification.innerHTML = `
        <div style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: linear-gradient(135deg, #724FF9 0%, #724FF9 100%); color: white; padding: 1rem 1.5rem; border-radius: 0.75rem; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); display: flex; align-items: center; gap: 1rem; animation: slideIn 0.3s ease;">
            <div>
                <p style="font-weight: 600; margin: 0 0 0.25rem 0;">Update Available</p>
                <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">A new version is ready to install</p>
            </div>
            <button onclick="updateServiceWorker()" style="background: white; color: #724FF9; border: none; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer;">
                Update
            </button>
            <button onclick="this.parentElement.parentElement.remove()" style="background: transparent; color: white; border: none; padding: 0.5rem; cursor: pointer; font-size: 1.25rem; line-height: 1;">
                ×
            </button>
        </div>
    `;
    document.body.appendChild(notification);
}

// Update service worker
function updateServiceWorker() {
    if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
    }
}

// Mobile Sidebar Toggle Functionality
function initMobileSidebar() {
    const leftSidebar = document.querySelector('.left-sidebar');
    const rightSidebar = document.querySelector('.right-sidebar, #chat-info-sidebar');
    const leftToggle = document.getElementById('left-sidebar-toggle');
    const rightToggle = document.getElementById('right-sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');

    // Toggle left sidebar
    if (leftToggle && leftSidebar) {
        leftToggle.addEventListener('click', () => {
            leftSidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
        });
    }

    // Toggle right sidebar
    if (rightToggle && rightSidebar) {
        rightToggle.addEventListener('click', () => {
            rightSidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
        });
    }

    // Close sidebars when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', () => {
            if (leftSidebar) leftSidebar.classList.remove('active');
            if (rightSidebar) rightSidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Close sidebars on window resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            if (leftSidebar) leftSidebar.classList.remove('active');
            if (rightSidebar) rightSidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
        }
    });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileSidebar);
} else {
    initMobileSidebar();
}

// Export functions for global use
window.updateServiceWorker = updateServiceWorker;
