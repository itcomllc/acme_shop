// Enhanced Theme Manager for SSL SaaS Platform - Fixed Version
class ThemeManager {
    constructor() {
        this.currentTheme = 'system';
        this.isInitialized = false;
        this.pendingCallbacks = [];
        this.observers = [];
        this.initPromise = null;
        this.isUpdating = false; // 無限ループ防止フラグ
        this.init();
    }

    init() {
        // 重複初期化を防ぐ
        if (this.initPromise) {
            return this.initPromise;
        }

        this.initPromise = new Promise((resolve) => {
            // 即座にテーマを適用（ページ読み込み前）
            this.applyStoredThemeImmediately();
            
            // DOMContentLoadedでも再度確認
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    this.completeInitialization();
                    resolve();
                });
            } else {
                // 少し遅延を入れて他のスクリプトの読み込みを待つ
                setTimeout(() => {
                    this.completeInitialization();
                    resolve();
                }, 50);
            }
        });

        return this.initPromise;
    }

    completeInitialization() {
        if (this.isInitialized) {
            return;
        }

        this.applyStoredTheme();
        this.watchSystemTheme();
        this.setupLivewireListeners();
        this.setupNavigationListeners();
        
        this.isInitialized = true;
        
        // 待機中のコールバックを実行
        this.pendingCallbacks.forEach(callback => {
            try {
                callback();
            } catch (error) {
                console.warn('Error in pending callback:', error);
            }
        });
        this.pendingCallbacks = [];
        
        console.log('ThemeManager fully initialized');
    }

    applyStoredThemeImmediately() {
        try {
            // localStorageから取得
            let storedTheme = localStorage.getItem('theme');
            
            // localStorageにない場合は、サーバーサイドのセッションから取得
            if (!storedTheme) {
                const metaTheme = document.querySelector('meta[name="theme"]');
                if (metaTheme) {
                    storedTheme = metaTheme.getAttribute('content');
                }
            }

            const theme = storedTheme || 'system';
            this.currentTheme = theme;
            console.log('Immediately applying theme:', theme);
            this.applyThemeToDOM(theme);
        } catch (error) {
            console.warn('Error immediately applying theme:', error);
            this.applyThemeToDOM('system');
        }
    }

    applyStoredTheme() {
        try {
            const theme = this.currentTheme || localStorage.getItem('theme') || 'system';
            console.log('Applying stored theme on DOM ready:', theme);
            this.applyThemeToDOM(theme);
        } catch (error) {
            console.warn('Error applying stored theme:', error);
            this.applyThemeToDOM('system');
        }
    }

    applyThemeToDOM(theme) {
        // 無限ループ防止
        if (this.isUpdating) {
            console.log('Theme update already in progress, skipping');
            return;
        }

        this.isUpdating = true;

        try {
            const html = document.documentElement;
            const body = document.body;
            
            console.log('ThemeManager: Applying theme to DOM:', theme);
            
            // 既存のテーマクラスを削除
            html.classList.remove('dark', 'light');
            if (body) {
                body.classList.remove('dark', 'light');
            }
            
            // data-theme属性も更新
            html.setAttribute('data-theme', theme);
            
            let effectiveTheme = theme;
            
            if (theme === 'dark') {
                html.classList.add('dark');
                if (body) body.classList.add('dark');
                effectiveTheme = 'dark';
                console.log('Dark theme applied');
            } else if (theme === 'light') {
                html.classList.add('light');  
                if (body) body.classList.add('light');
                effectiveTheme = 'light';
                console.log('Light theme applied');
            } else if (theme === 'system') {
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) {
                    html.classList.add('dark');
                    if (body) body.classList.add('dark');
                    effectiveTheme = 'dark';
                    console.log('System dark theme applied');
                } else {
                    html.classList.add('light');
                    if (body) body.classList.add('light');
                    effectiveTheme = 'light';
                    console.log('System light theme applied');
                }
            }
            
            // 現在のテーマを保存
            this.currentTheme = theme;
            
            // localStorageに保存
            try {
                localStorage.setItem('theme', theme);
                console.log('Theme saved to localStorage:', theme);
            } catch (e) {
                console.warn('Could not save theme to localStorage:', e);
            }

            // サーバーサイドと同期（セッション更新）- デバウンス処理
            this.debouncedSyncWithServer(theme);

            // オブザーバーに通知
            this.notifyObservers(theme, effectiveTheme);

            // カスタムイベントを発火
            window.dispatchEvent(new CustomEvent('theme-applied', { 
                detail: { theme, effectiveTheme } 
            }));

        } finally {
            // 少し遅延してフラグをリセット
            setTimeout(() => {
                this.isUpdating = false;
            }, 100);
        }
    }

    // デバウンス処理でサーバー同期の頻度を制限
    debouncedSyncWithServer = this.debounce((theme) => {
        this.syncWithServer(theme);
    }, 500);

    syncWithServer(theme) {
        // サーバーサイドのセッションと同期
        if (typeof fetch !== 'undefined') {
            fetch('/api/user/preferences', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ theme: theme })
            }).catch(error => {
                console.log('Theme sync with server failed (not critical):', error);
            });
        }
    }

    watchSystemTheme() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            const handleChange = (e) => {
                const currentTheme = this.getCurrentTheme();
                if (currentTheme === 'system') {
                    console.log('System color scheme changed, reapplying theme');
                    this.applyThemeToDOM('system');
                }
            };

            // 新しいAPIを使用（古いAPIのフォールバック付き）
            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', handleChange);
            } else {
                mediaQuery.addListener(handleChange);
            }
        }
    }

    setupLivewireListeners() {
        // Livewireが利用可能になった時点でリスナーを設定
        const setupListeners = () => {
            if (typeof Livewire !== 'undefined') {
                try {
                    // 既存のリスナーを削除（重複を防ぐ）
                    if (this._livewireListenersSetup) {
                        return;
                    }
                    this._livewireListenersSetup = true;

                    // Livewire 3の新しいイベントシステムを使用
                    document.addEventListener('livewire:init', () => {
                        // theme-changedイベントリスナー
                        Livewire.on('theme-changed', (event) => {
                            console.log('Livewire theme-changed event:', event);
                            const theme = Array.isArray(event) ? event[0]?.theme : event.theme;
                            if (theme && !this.isUpdating) {
                                this.applyThemeToDOM(theme);
                            }
                        });

                        // appearance-updatedイベントリスナー
                        Livewire.on('appearance-updated', (event) => {
                            console.log('Livewire appearance-updated event');
                            const theme = Array.isArray(event) ? event[0]?.theme : event.theme;
                            if (theme && !this.isUpdating) {
                                this.applyThemeToDOM(theme);
                            }
                        });
                    });

                    console.log('Livewire theme listeners setup completed');
                } catch (error) {
                    console.warn('Error setting up Livewire listeners:', error);
                }
            }
        };

        // 複数のタイミングで試行
        document.addEventListener('livewire:init', setupListeners);
        document.addEventListener('livewire:navigated', setupListeners);
        
        // 即座に試行
        setupListeners();
        
        // 少し待ってから再試行
        setTimeout(setupListeners, 100);
    }

    setupNavigationListeners() {
        // Livewire navigate イベント
        document.addEventListener('livewire:navigate', () => {
            console.log('Livewire navigate - preserving theme');
            // ナビゲーション中はテーマを保持
        });

        // Livewire navigated イベント  
        document.addEventListener('livewire:navigated', () => {
            console.log('Livewire navigated - reapplying theme');
            // ナビゲーション完了後にテーマを再適用
            if (!this.isUpdating) {
                setTimeout(() => {
                    this.applyThemeToDOM(this.currentTheme);
                }, 10);
            }
        });

        // ページが表示されたときの処理
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isUpdating) {
                // ページが再表示されたときにテーマを確認
                setTimeout(() => {
                    this.applyThemeToDOM(this.currentTheme);
                }, 50);
            }
        });

        // ページフォーカス時の処理
        window.addEventListener('focus', () => {
            if (!this.isUpdating) {
                setTimeout(() => {
                    this.applyThemeToDOM(this.currentTheme);
                }, 50);
            }
        });

        // 通常のページ遷移も監視
        window.addEventListener('beforeunload', () => {
            // ページ遷移前にテーマを保存
            localStorage.setItem('theme', this.currentTheme);
        });
    }

    // オブザーバーパターンの実装
    addObserver(callback) {
        this.observers.push(callback);
    }

    removeObserver(callback) {
        this.observers = this.observers.filter(obs => obs !== callback);
    }

    notifyObservers(theme, effectiveTheme) {
        this.observers.forEach(observer => {
            try {
                observer(theme, effectiveTheme);
            } catch (error) {
                console.warn('Error in theme observer:', error);
            }
        });
    }

    // 外部から呼び出し可能なメソッド
    setTheme(theme) {
        if (['light', 'dark', 'system'].includes(theme) && !this.isUpdating) {
            console.log('Setting theme externally:', theme);
            this.applyThemeToDOM(theme);
            
            // Livewireコンポーネントが存在する場合は同期（デバウンス）
            if (typeof Livewire !== 'undefined') {
                this.debouncedSyncWithLivewire(theme);
            }
        } else if (this.isUpdating) {
            console.log('Theme update already in progress, ignoring setTheme call');
        } else {
            console.warn('Invalid theme:', theme);
        }
    }

    // デバウンス処理でLivewire同期の頻度を制限
    debouncedSyncWithLivewire = this.debounce((theme) => {
        try {
            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('theme-changed-externally', { theme });
            }
        } catch (e) {
            console.warn('Could not sync theme with Livewire:', e);
        }
    }, 200);

    getCurrentTheme() {
        return this.currentTheme || localStorage.getItem('theme') || 'system';
    }

    getEffectiveTheme() {
        const theme = this.getCurrentTheme();
        if (theme === 'system') {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return theme;
    }

    // 強制的にテーマを再適用
    forceReapply() {
        if (!this.isUpdating) {
            console.log('Force reapplying theme:', this.currentTheme);
            this.applyThemeToDOM(this.currentTheme);
        }
    }

    // 初期化完了を待つ（Promiseベース）
    whenReady(callback) {
        if (callback && typeof callback === 'function') {
            if (this.isInitialized) {
                try {
                    callback();
                } catch (error) {
                    console.warn('Error in theme ready callback:', error);
                }
            } else if (this.initPromise) {
                this.initPromise.then(() => {
                    try {
                        callback();
                    } catch (error) {
                        console.warn('Error in theme ready callback:', error);
                    }
                });
            } else {
                this.pendingCallbacks.push(callback);
            }
        }
        return this.initPromise;
    }

    // デバウンス関数
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // クリーンアップ
    destroy() {
        this.observers = [];
        this.pendingCallbacks = [];
        this._livewireListenersSetup = false;
        this.isInitialized = false;
        this.initPromise = null;
        this.isUpdating = false;
    }
}

// ThemeManagerの初期化を安全に行う
let themeManagerInstance = null;

function initializeThemeManager() {
    if (!themeManagerInstance) {
        console.log('Initializing ThemeManager...');
        themeManagerInstance = new ThemeManager();
        
        // グローバルに利用可能にする
        window.ThemeManager = themeManagerInstance;
        
        // 便利なヘルパー関数（エラーハンドリング付き）
        window.setTheme = (theme) => {
            try {
                if (window.ThemeManager && !window.ThemeManager.isUpdating) {
                    window.ThemeManager.setTheme(theme);
                    return true;
                } else if (window.ThemeManager && window.ThemeManager.isUpdating) {
                    console.log('Theme update in progress, queuing theme setting');
                    setTimeout(() => {
                        if (window.ThemeManager && !window.ThemeManager.isUpdating) {
                            window.ThemeManager.setTheme(theme);
                        }
                    }, 200);
                    return false;
                } else {
                    console.warn('ThemeManager not available, queuing theme setting');
                    // ThemeManagerが利用可能になるまで待機
                    setTimeout(() => {
                        if (window.ThemeManager) {
                            window.ThemeManager.setTheme(theme);
                        }
                    }, 100);
                    return false;
                }
            } catch (error) {
                console.error('Error setting theme:', error);
                return false;
            }
        };
        
        window.getCurrentTheme = () => {
            try {
                return window.ThemeManager ? window.ThemeManager.getCurrentTheme() : 'system';
            } catch (error) {
                console.error('Error getting current theme:', error);
                return 'system';
            }
        };
        
        window.getEffectiveTheme = () => {
            try {
                return window.ThemeManager ? window.ThemeManager.getEffectiveTheme() : 'system';
            } catch (error) {
                console.error('Error getting effective theme:', error);
                return 'system';
            }
        };
        
        // デバッグ用
        window.forceReapplyTheme = () => {
            try {
                if (window.ThemeManager && !window.ThemeManager.isUpdating) {
                    window.ThemeManager.forceReapply();
                    return true;
                }
                return false;
            } catch (error) {
                console.error('Error force reapplying theme:', error);
                return false;
            }
        };

        // 初期化完了を待つヘルパー（Promiseベース）
        window.whenThemeReady = (callback) => {
            try {
                if (window.ThemeManager) {
                    return window.ThemeManager.whenReady(callback);
                } else {
                    return new Promise((resolve) => {
                        setTimeout(() => {
                            if (window.ThemeManager) {
                                window.ThemeManager.whenReady(callback).then(resolve);
                            } else {
                                resolve();
                            }
                        }, 100);
                    });
                }
            } catch (error) {
                console.error('Error in whenThemeReady:', error);
                return Promise.resolve();
            }
        };

        console.log('ThemeManager helper functions initialized');
    }
    
    return themeManagerInstance;
}

// 複数回の初期化を防ぐフラグ
if (!window._themeManagerInitialized) {
    window._themeManagerInitialized = true;
    
    // 即座に初期化を開始
    const manager = initializeThemeManager();

    // DOMContentLoadedでも確実に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM loaded - ensuring ThemeManager initialization');
            initializeThemeManager();
        });
    } else {
        console.log('DOM already loaded - ThemeManager initialized');
    }

    // ページ読み込み完了後にも再確認
    window.addEventListener('load', () => {
        console.log('Page fully loaded, ensuring theme manager is ready');
        if (window.ThemeManager && !window.ThemeManager.isUpdating) {
            window.ThemeManager.forceReapply();
        } else if (!window.ThemeManager) {
            console.warn('ThemeManager not available on window load');
            initializeThemeManager();
        }
    });

    // エラーイベントの監視
    window.addEventListener('error', (event) => {
        // ThemeManager関連のエラーをキャッチ
        if (event.error && event.error.message && 
            event.error.message.includes('ThemeManager')) {
            console.warn('ThemeManager error caught:', event.error.message);
            // エラーが発生した場合は再初期化を試行
            setTimeout(() => {
                if (!window.ThemeManager || !window.ThemeManager.isInitialized) {
                    console.log('Attempting ThemeManager recovery...');
                    window._themeManagerInitialized = false;
                    themeManagerInstance = null;
                    initializeThemeManager();
                }
            }, 1000);
        }
    });
}

// テーマ適用の確認用
console.log('Enhanced Theme Manager script loaded and initialized');

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}