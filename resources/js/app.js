import './bootstrap';
import './theme-manager'; // シンプルなtheme-manager.jsをインポート
import '../css/app.css';

// Alpine.jsの初期化（重複防止版）
if (!window.Alpine && !window._alpineInitialized) {
    window._alpineInitialized = true;
    console.log('Loading Alpine.js...');
    import('alpinejs').then((Alpine) => {
        if (!window.Alpine) {
            window.Alpine = Alpine.default;
            Alpine.default.start();
            console.log('Alpine.js initialized by app.js');
        } else {
            console.log('Alpine.js already exists, skipping initialization');
        }
    }).catch(error => {
        console.warn('Failed to load Alpine.js:', error);
        window._alpineInitialized = false;
    });
} else {
    console.log('Alpine.js already initialized or in progress');
}

// DOMContentLoaded時の基本処理のみ
document.addEventListener('DOMContentLoaded', function() {
    console.log('App.js DOM loaded');

    // 基本的な機能のみ設定
    setupNotificationAutoHide();
    setupClipboardFunctionality();
});

// Livewireナビゲーション時の処理（最小限）
document.addEventListener('livewire:navigated', () => {
    console.log('Livewire navigated - reapplying basic functions');
    setupNotificationAutoHide();
});

// 通知の自動非表示機能（シンプル版）
function setupNotificationAutoHide() {
    const notifications = document.querySelectorAll('.notification, .bg-green-500, .bg-red-500, .bg-yellow-500, .bg-blue-500');
    notifications.forEach(notification => {
        // 既に処理済みの場合はスキップ
        if (notification.dataset.autoHideSetup) {
            return;
        }
        notification.dataset.autoHideSetup = 'true';
        
        setTimeout(() => {
            if (notification && notification.parentNode) {
                notification.style.transition = 'opacity 0.3s ease-out';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    });
}

// クリップボード機能（シンプル版）
function setupClipboardFunctionality() {
    window.copyToClipboard = function(text, buttonId) {
        const button = buttonId ? document.getElementById(buttonId) : null;
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                updateCopyButton(button, 'Copied!', 'success');
            }).catch(err => {
                console.warn('Failed to copy to clipboard:', err);
                fallbackCopyToClipboard(text, button);
            });
        } else {
            fallbackCopyToClipboard(text, button);
        }
    };
}

// フォールバッククリップボード機能
function fallbackCopyToClipboard(text, button) {
    try {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        updateCopyButton(button, 'Copied!', 'success');
    } catch (fallbackErr) {
        console.error('Copy to clipboard failed:', fallbackErr);
        updateCopyButton(button, 'Copy failed', 'error');
    }
}

// コピーボタンの更新
function updateCopyButton(button, message, type) {
    if (button) {
        const originalText = button.textContent;
        const originalClasses = button.className;
        
        button.textContent = message;
        
        if (type === 'success') {
            button.className = button.className.replace(/bg-\w+-\d+/, 'bg-green-500');
        } else if (type === 'error') {
            button.className = button.className.replace(/bg-\w+-\d+/, 'bg-red-500');
        }
        
        setTimeout(() => {
            button.textContent = originalText;
            button.className = originalClasses;
        }, 2000);
    }
}

// バナーメッセージシステム（Livewire互換）
window.addEventListener('banner-message', (event) => {
    const { style, message } = event.detail;
    showNotification(message, style);
});

// 通知表示機能（シンプル版）
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    
    // タイプに応じてクラスを追加
    switch (type) {
        case 'success':
            notification.classList.add('bg-green-500', 'text-white');
            break;
        case 'error':
            notification.classList.add('bg-red-500', 'text-white');
            break;
        case 'warning':
            notification.classList.add('bg-yellow-500', 'text-white');
            break;
        default:
            notification.classList.add('bg-blue-500', 'text-white');
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // 自動削除
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// SSL Dashboard固有の機能（シンプル版）
window.sslDashboard = {
    refreshCertificateStatus: function(certificateId) {
        if (typeof Livewire !== 'undefined') {
            try {
                Livewire.dispatch('refresh-certificate-status', { certificateId });
            } catch (e) {
                console.warn('Failed to refresh certificate status:', e);
            }
        }
    },
    
    downloadCertificate: function(certificateId) {
        const url = `/ssl/certificate/${certificateId}/download`;
        const link = document.createElement('a');
        link.href = url;
        link.download = `certificate-${certificateId}.pem`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },
    
    copyValidationCode: function(code, buttonId) {
        window.copyToClipboard(code, buttonId);
    }
};

// エラーハンドリング（最小限）
window.addEventListener('error', (event) => {
    // 重要なエラーのみログ出力
    if (event.error && event.error.message && 
        !event.error.message.includes('Alpine') && 
        !event.error.message.includes('Non-critical')) {
        console.error('Application error:', event.error.message);
    }
});

console.log('Simple App.js loaded successfully');