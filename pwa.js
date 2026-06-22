// ========================================
// 💼 BIZFLOW - PWA Install Helper
// Handles install prompts + service worker
// ========================================

let deferredPrompt = null;
let installButton = null;

// ========================================
// 📲 REGISTER SERVICE WORKER
// ========================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('✅ BizFlow Service Worker registered:', registration.scope);
                
                // Check for updates every hour
                setInterval(() => {
                    registration.update();
                }, 60 * 60 * 1000);
                
                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateBanner();
                        }
                    });
                });
            })
            .catch(err => {
                console.warn('❌ Service Worker registration failed:', err);
            });
    });
}

// ========================================
// 💾 INSTALL PROMPT HANDLER
// ========================================
window.addEventListener('beforeinstallprompt', (e) => {
    console.log('💾 BizFlow install prompt available');
    e.preventDefault();
    deferredPrompt = e;
    showInstallBanner();
});

// ========================================
// 🎉 APP INSTALLED
// ========================================
window.addEventListener('appinstalled', () => {
    console.log('🎉 BizFlow installed successfully!');
    hideInstallBanner();
    deferredPrompt = null;
    
    // Track install
    if (typeof showToast === 'function') {
        showToast('🎉 BizFlow installed! Find it on your home screen.', 'success');
    }
});

// ========================================
// 🎨 SHOW INSTALL BANNER
// ========================================
function showInstallBanner() {
    // Don't show if already installed
    if (window.matchMedia('(display-mode: standalone)').matches) {
        return;
    }
    
    // Don't show again if dismissed
    if (localStorage.getItem('bizflow_install_dismissed') === 'true') {
        return;
    }
    
    const banner = document.createElement('div');
    banner.id = 'pwa-install-banner';
    banner.innerHTML = `
        <div style="display:flex;align-items:center;gap:14px;flex:1;">
            <div style="width:48px;height:48px;background:linear-gradient(135deg,#3b82f6,#60a5fa);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">📱</div>
            <div>
                <div style="font-weight:800;font-size:14px;color:white;">Install BizFlow</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Get the app for faster access</div>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <button id="pwa-install-btn" style="background:linear-gradient(135deg,#3b82f6,#60a5fa);color:white;border:none;padding:10px 18px;border-radius:10px;font-weight:700;cursor:pointer;font-size:13px;">Install</button>
            <button id="pwa-dismiss-btn" style="background:rgba(255,255,255,0.1);color:white;border:none;padding:10px 14px;border-radius:10px;cursor:pointer;font-size:13px;">✕</button>
        </div>
    `;
    
    banner.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 20px;
        right: 20px;
        max-width: 480px;
        margin: 0 auto;
        background: rgba(26,31,51,0.98);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 18px;
        padding: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        z-index: 99999;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        animation: pwaSlideUp 0.4s ease;
    `;
    
    document.body.appendChild(banner);
    
    // Add animation
    if (!document.getElementById('pwa-styles')) {
        const style = document.createElement('style');
        style.id = 'pwa-styles';
        style.textContent = `
            @keyframes pwaSlideUp {
                from { transform: translateY(100px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @media (max-width: 640px) {
                #pwa-install-banner {
                    bottom: 10px !important;
                    left: 10px !important;
                    right: 10px !important;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Install button
    document.getElementById('pwa-install-btn').addEventListener('click', async () => {
        if (!deferredPrompt) return;
        
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        
        console.log(`PWA install outcome: ${outcome}`);
        
        if (outcome === 'accepted') {
            console.log('🎉 User accepted install');
        }
        
        deferredPrompt = null;
        hideInstallBanner();
    });
    
    // Dismiss button
    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
        localStorage.setItem('bizflow_install_dismissed', 'true');
        hideInstallBanner();
    });
}

function hideInstallBanner() {
    const banner = document.getElementById('pwa-install-banner');
    if (banner) {
        banner.style.animation = 'pwaSlideUp 0.3s reverse';
        setTimeout(() => banner.remove(), 300);
    }
}

// ========================================
// 🔄 UPDATE BANNER (when new version available)
// ========================================
function showUpdateBanner() {
    const banner = document.createElement('div');
    banner.innerHTML = `
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="font-size:24px;">🆕</div>
            <div>
                <div style="font-weight:800;font-size:13px;color:white;">Update Available</div>
                <div style="font-size:11px;color:#94a3b8;">New version of BizFlow</div>
            </div>
        </div>
        <button onclick="location.reload()" style="background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;padding:8px 16px;border-radius:8px;font-weight:700;cursor:pointer;font-size:12px;">Update</button>
    `;
    
    banner.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(26,31,51,0.98);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(16,185,129,0.5);
        border-radius: 14px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        z-index: 99999;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    `;
    
    document.body.appendChild(banner);
}

// ========================================
// 📊 CHECK INSTALL STATUS
// ========================================
window.addEventListener('DOMContentLoaded', () => {
    if (window.matchMedia('(display-mode: standalone)').matches) {
        console.log('📱 BizFlow running as installed PWA');
        document.body.classList.add('pwa-installed');
    }
});

// ========================================
// 🔔 NOTIFICATION PERMISSION
// ========================================
function requestNotificationPermission() {
    if (!('Notification' in window)) return;
    
    if (Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            console.log('Notification permission:', permission);
        });
    }
}

// Auto-request after 30 seconds of use
setTimeout(requestNotificationPermission, 30000);
