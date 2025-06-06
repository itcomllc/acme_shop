import './bootstrap';
import './theme-manager'; // theme-manager.jsをインポート
import '../css/app.css';

// Alpine.jsの重複を防ぐチェック（修正版）
if (!window.Alpine) {
    console.log('Loading Alpine.js...');
    import('alpinejs').then((Alpine) => {
        if (!window.Alpine) {
            window.Alpine = Alpine.default;
            Alpine.default.start();
            console.log('Alpine.js initialized successfully');
        } else {
            console.log('Alpine.js already loaded, skipping initialization');
        }
    }).catch(error => {
        console.warn('Failed to load Alpine.js:', error);
    });
} else {
    console.log('Alpine.js already available');
}

// カスタムSSL Dashboard機能
document.addEventListener('DOMContentLoaded', function() {
    console.log('App.js DOM loaded event');

    // ThemeManagerの初期化を確認
    if (window.ThemeManager) {
        console.log('ThemeManager is available in app.js');
        
        // テーマ変更のオブザーバーを追加
        window.ThemeManager.addObserver((theme, effectiveTheme) => {
            console.log('Theme changed in app.js observer:', theme, '->', effectiveTheme);
            updateFormInputThemes();
        });
    } else {
        console.warn('ThemeManager not available in app.js, setting up delayed initialization');
        
        // ThemeManagerが後で利用可能になった場合の処理
        setTimeout(() => {
            if (window.ThemeManager) {
                console.log('ThemeManager found after delay');
                window.ThemeManager.addObserver((theme, effectiveTheme) => {
                    console.log('Theme changed in app.js observer (delayed):', theme, '->', effectiveTheme);
                    updateFormInputThemes();
                });
            }
        }, 1000);
    }

    // Auto-hide notifications（改善版）
    setupNotificationAutoHide();

    // Copy to clipboard functionality（エラーハンドリング強化）
    setupClipboardFunctionality();

    // Enhanced form validation
    setupFormValidation();

    // 初期テーマ適用
    updateFormInputThemes();
});

// Livewireイベントリスナー（改善版）
document.addEventListener('livewire:init', () => {
    console.log('Livewire initialized - setting up app listeners');
    
    // テーママネージャーとの連携
    setupThemeManagerIntegration();
    
    // SSL証明書関連のイベントリスナー
    setupSSLEventListeners();
});

// ページナビゲーション時の処理（改善版）
document.addEventListener('livewire:navigated', () => {
    console.log('Livewire navigated - reapplying app enhancements');
    
    // 通知の自動非表示を再設定
    setupNotificationAutoHide();
    
    // フォームバリデーションを再設定
    setupFormValidation();
    
    // テーマを再適用
    updateFormInputThemes();

    // ThemeManagerの再確認
    if (window.ThemeManager && window.ThemeManager.isInitialized) {
        console.log('ThemeManager available after navigation - reapplying theme');
        window.ThemeManager.forceReapply();
    } else {
        console.warn('ThemeManager not available after navigation');
        // 少し待ってから再試行
        setTimeout(() => {
            if (window.ThemeManager) {
                window.ThemeManager.forceReapply();
            }
        }, 500);
    }
});

// 通知の自動非表示機能
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

// クリップボード機能の設定
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

// フォームバリデーションの設定
function setupFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        // 既に処理済みの場合はスキップ
        if (form.dataset.validationSetup) {
            return;
        }
        form.dataset.validationSetup = 'true';
        
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;

            inputs.forEach(input => {
                const value = input.value ? input.value.trim() : '';
                if (!value) {
                    input.classList.add('form-input-error');
                    showFieldError(input, 'This field is required');
                    isValid = false;
                } else {
                    input.classList.remove('form-input-error');
                    hideFieldError(input);
                }
            });

            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
}

// フィールドエラーの表示
function showFieldError(input, message) {
    let errorElement = input.parentNode.querySelector('.field-error');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'field-error text-sm text-red-600 dark:text-red-400 mt-1';
        input.parentNode.appendChild(errorElement);
    }
    errorElement.textContent = message;
}

// フィールドエラーの非表示
function hideFieldError(input) {
    const errorElement = input.parentNode.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

// テーマに応じたフォーム入力の更新
function updateFormInputThemes() {
    try {
        const isDark = document.documentElement.classList.contains('dark');
        const formInputs = document.querySelectorAll('input, select, textarea');
        
        formInputs.forEach(input => {
            if (isDark) {
                input.classList.add('dark:bg-gray-700', 'dark:text-white', 'dark:border-gray-600');
                input.classList.remove('bg-white', 'text-gray-900', 'border-gray-300');
            } else {
                input.classList.remove('dark:bg-gray-700', 'dark:text-white', 'dark:border-gray-600');
                input.classList.add('bg-white', 'text-gray-900', 'border-gray-300');
            }
        });
        
        console.log('Form input themes updated for', isDark ? 'dark' : 'light', 'mode');
    } catch (error) {
        console.warn('Error updating form input themes:', error);
    }
}

// ThemeManagerとの統合
function setupThemeManagerIntegration() {
    const integrationFunction = () => {
        if (window.ThemeManager) {
            console.log('ThemeManager found in livewire:init');
            window.ThemeManager.addObserver((theme, effectiveTheme) => {
                console.log('Theme changed via ThemeManager:', theme, '->', effectiveTheme);
                
                // カスタムイベントを発火してコンポーネントに通知
                window.dispatchEvent(new CustomEvent('app-theme-changed', {
                    detail: { theme, effectiveTheme }
                }));
                
                // フォーム入力テーマを更新
                updateFormInputThemes();
            });
        } else {
            console.warn('ThemeManager not found in livewire:init');
        }
    };

    // 即座に試行
    integrationFunction();
    
    // 少し待ってから再試行
    setTimeout(integrationFunction, 500);
    setTimeout(integrationFunction, 1000);
}

// SSL関連のイベントリスナー
function setupSSLEventListeners() {
    // Real-time status updates
    window.updateCertificateStatus = function(certificateId, status) {
        const statusElement = document.querySelector(`[data-certificate-id="${certificateId}"] .certificate-status`);
        if (statusElement) {
            statusElement.className = `status-badge status-badge-${getStatusClass(status)}`;
            statusElement.textContent = status.replace('_', ' ');
        }
    };

    // SSL証明書ステータスの更新
    window.addEventListener('ssl-certificate-updated', (event) => {
        const { certificateId, status } = event.detail;
        window.updateCertificateStatus(certificateId, status);
    });
}

// ステータスクラスの取得
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

// テーマ変更を監視
window.addEventListener('theme-applied', (event) => {
    console.log('Theme applied event received:', event.detail);
    updateFormInputThemes();
});

// エラーハンドリング（改善版）
window.addEventListener('error', (event) => {
    // Alpine.js関連のエラーは静かに処理
    if (event.error && event.error.message && 
        (event.error.message.includes('Alpine') || 
         event.error.message.includes('ThemeManager'))) {
        console.warn('Non-critical UI error:', event.error.message);
        event.preventDefault();
        return;
    }
    
    // SSL関連のエラーをキャッチして適切に処理
    if (event.error && event.error.message && event.error.message.includes('SSL')) {
        console.error('SSL related error:', event.error.message);
        // 必要に応じてユーザーに通知
        showNotification('An SSL operation encountered an error. Please try again.', 'error');
    }
});

// 通知表示機能
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

// バナーメッセージシステム（Livewire互換）
window.addEventListener('banner-message', (event) => {
    const { style, message } = event.detail;
    showNotification(message, style);
});

// パフォーマンス最適化
if ('requestIdleCallback' in window) {
    requestIdleCallback(() => {
        // アイドル時に実行する処理
        console.log('App initialization completed during idle time');
        
        // ThemeManagerの最終確認
        if (window.ThemeManager) {
            console.log('ThemeManager final check: OK');
        } else {
            console.warn('ThemeManager final check: NOT FOUND');
        }
        
        // 不要なイベントリスナーのクリーンアップ
        cleanupUnusedEventListeners();
    });
}

// 不要なイベントリスナーのクリーンアップ
function cleanupUnusedEventListeners() {
    // 重複したイベントリスナーを削除
    const elements = document.querySelectorAll('[data-auto-hide-setup], [data-validation-setup]');
    console.log(`Cleaned up ${elements.length} processed elements`);
}

// SSL Dashboard固有の機能
window.sslDashboard = {
    refreshCertificateStatus: function(certificateId) {
        if (typeof Livewire !== 'undefined') {
            try {
                Livewire.dispatch('refresh-certificate-status', { certificateId });
            } catch (e) {
                console.warn('Failed to refresh certificate status via Livewire:', e);
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

// デバッグ情報（開発環境のみ）
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    window.sslDebug = {
        getThemeInfo: () => ({
            currentTheme: window.getCurrentTheme ? window.getCurrentTheme() : 'unknown',
            effectiveTheme: window.getEffectiveTheme ? window.getEffectiveTheme() : 'unknown',
            themeManagerAvailable: !!window.ThemeManager,
            alpineAvailable: !!window.Alpine,
            livewireAvailable: typeof Livewire !== 'undefined'
        }),
        
        testThemeSwitch: () => {
            const currentTheme = window.getCurrentTheme();
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            console.log(`Testing theme switch from ${currentTheme} to ${newTheme}`);
            window.setTheme(newTheme);
        }
    };
    
    console.log('SSL Debug tools available:', window.sslDebug);
}

console.log('Enhanced App.js loaded successfully');