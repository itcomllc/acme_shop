import './bootstrap';
import '../css/app.css';

// Alpine.jsの重複を防ぐチェック
if (!window.Alpine) {
    import('alpinejs').then((Alpine) => {
        window.Alpine = Alpine.default;
        Alpine.default.start();
        console.log('Alpine.js initialized');
    }).catch(error => {
        console.warn('Failed to load Alpine.js:', error);
    });
} else {
    console.log('Alpine.js already loaded');
}

// カスタムSSL Dashboard機能
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide notifications
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        setTimeout(() => {
            if (notification && notification.parentNode) {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    });

    // Copy to clipboard functionality
    window.copyToClipboard = function(text, buttonId) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                const button = document.getElementById(buttonId);
                if (button) {
                    const originalText = button.textContent;
                    const originalClasses = button.className;
                    button.textContent = 'Copied!';
                    button.className = button.className.replace(/bg-\w+-\d+/, 'bg-green-500');
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.className = originalClasses;
                    }, 2000);
                }
            }).catch(err => {
                console.warn('Failed to copy to clipboard:', err);
                // フォールバック：テキストを選択
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    
                    const button = document.getElementById(buttonId);
                    if (button) {
                        const originalText = button.textContent;
                        button.textContent = 'Copied!';
                        setTimeout(() => {
                            button.textContent = originalText;
                        }, 2000);
                    }
                } catch (fallbackErr) {
                    console.error('Copy to clipboard failed:', fallbackErr);
                }
            });
        }
    };

    // Real-time status updates
    window.updateCertificateStatus = function(certificateId, status) {
        const statusElement = document.querySelector(`[data-certificate-id="${certificateId}"] .certificate-status`);
        if (statusElement) {
            statusElement.className = `status-badge status-badge-${getStatusClass(status)}`;
            statusElement.textContent = status.replace('_', ' ');
        }
    };

    function getStatusClass(status) {
        const statusMap = {
            'issued': 'issued',
            'pending_validation': 'pending',
            'processing': 'processing',
            'expired': 'expired',
            'revoked': 'revoked',
            'failed': 'failed'
        };
        return statusMap[status] || 'pending';
    }

    // Enhanced form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('form-input-error');
                    isValid = false;
                } else {
                    input.classList.remove('form-input-error');
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    // Theme-aware form inputs
    function updateFormInputThemes() {
        const isDark = document.documentElement.classList.contains('dark');
        const formInputs = document.querySelectorAll('input, select, textarea');
        
        formInputs.forEach(input => {
            if (isDark) {
                input.classList.add('dark:bg-gray-700', 'dark:text-white', 'dark:border-gray-600');
            } else {
                input.classList.remove('dark:bg-gray-700', 'dark:text-white', 'dark:border-gray-600');
            }
        });
    }

    // テーマ変更を監視
    window.addEventListener('theme-applied', updateFormInputThemes);
    
    // 初期適用
    updateFormInputThemes();
});

// Livewireイベントリスナー
document.addEventListener('livewire:init', () => {
    console.log('Livewire initialized - setting up app listeners');
    
    // テーママネージャーとの連携
    if (window.ThemeManager) {
        window.ThemeManager.addObserver((theme, effectiveTheme) => {
            console.log('Theme changed via ThemeManager:', theme, '->', effectiveTheme);
            
            // カスタムイベントを発火してコンポーネントに通知
            window.dispatchEvent(new CustomEvent('app-theme-changed', {
                detail: { theme, effectiveTheme }
            }));
        });
    }
});

// ページナビゲーション時の処理
document.addEventListener('livewire:navigated', () => {
    console.log('Livewire navigated - reapplying app enhancements');
    
    // 通知の自動非表示を再設定
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        setTimeout(() => {
            if (notification && notification.parentNode) {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    });
});

// エラーハンドリング
window.addEventListener('error', (event) => {
    // テーマ関連のエラーは静かに処理
    if (event.error && event.error.message && 
        (event.error.message.includes('ThemeManager') || 
         event.error.message.includes('Alpine'))) {
        console.warn('Non-critical UI error:', event.error.message);
        event.preventDefault();
    }
});

// パフォーマンス最適化
if ('requestIdleCallback' in window) {
    requestIdleCallback(() => {
        // アイドル時に実行する処理
        console.log('App initialization completed during idle time');
    });
}

console.log('Enhanced App.js loaded successfully');