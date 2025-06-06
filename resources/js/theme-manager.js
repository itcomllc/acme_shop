// Enhanced Theme Manager for SSL SaaS Platform - Fixed Version
class ThemeManager {
    constructor() {
        this.currentTheme = 'system';
        this.isInitialized = false;
        this.pendingCallbacks = [];
        this.observers = [];
        this.initPromise = null;
        this.mediaQueryListener = null;
        this._livewireListenersSetup = false;
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
            
            // DOMContentLoadedで完全初期化
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
                }, 100);
            }
        });

        return this.initPromise;
    }

    completeInitialization() {
        if (this.isInitialized) {
            return;
        }

        try {
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
        } catch (error) {
            console.error('ThemeManager initialization failed:', error);
        }
    }

    applyStoredThemeImmediately() {
        try {
            // localStorageから取得
            let storedTheme = null;
            try {
                storedTheme = localStorage.getItem('theme');
            } catch (e) {
                console.warn('localStorage not available:', e);
            }
            
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
            const theme = this.currentTheme || this.getStoredTheme() || 'system';
            console.log('Applying stored theme on DOM ready:', theme);
            this.applyThemeToDOM(theme);
        } catch (error) {
            console.warn('Error applying stored theme:', error);
            this.applyThemeToDOM('system');
        }
    }

    getStoredTheme() {
        try {
            return localStorage.getItem('theme');
        } catch (e) {
            console.warn('Cannot access localStorage:', e);
            return null;
        }
    }

    applyThemeToDOM(theme) {
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
            const prefersDark = this.getSystemPreference();
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
        
        // localStorageに保存（エラーハンドリング付き）
        this.saveThemeToStorage(theme);

        // サーバーサイドと同期（セッション更新）
        this.syncWithServer(theme);

        // オブザーバーに通知
        this.notifyObservers(theme, effectiveTheme);

        // カスタムイベントを発火
        this.dispatchThemeEvent(theme, effectiveTheme);
    }

    getSystemPreference() {
        try {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        } catch (e) {
            console.warn('Cannot access matchMedia:', e);
            return false;
        }
    }

    saveThemeToStorage(theme) {
        try {
            localStorage.setItem('theme', theme);
            console.log('Theme saved to localStorage:', theme);
        } catch (e) {
            console.warn('Could not save theme to localStorage:', e);
        }
    }

    dispatchThemeEvent(theme, effectiveTheme) {
        try {
            window.dispatchEvent(new CustomEvent('theme-applied', { 
                detail: { theme, effectiveTheme } 
            }));
        } catch (e) {
            console.warn('Could not dispatch theme event:', e);
        }
    }

    syncWithServer(theme) {
        // サーバーサイドのセッションと同期
        if (typeof fetch !== 'undefined') {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (csrfToken) {
                    fetch('/api/user/preferences', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ theme: theme })
                    }).catch(error => {
                        console.log('Theme sync with server failed (not critical):', error);
                    });
                }
            } catch (error) {
                console.log('Theme sync error (not critical):', error);
            }
        }
    }

    watchSystemTheme() {
        if (!window.matchMedia) {
            return;
        }

        try {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            const handleChange = (e) => {
                const currentTheme = this.getCurrentTheme();
                if (currentTheme === 'system') {
                    console.log('System color scheme changed, reapplying theme');
                    this.applyThemeToDOM('system');
                }
            };

            // 既存のリスナーを削除
            if (this.mediaQueryListener) {
                if (mediaQuery.removeEventListener) {
                    mediaQuery.removeEventListener('change', this.mediaQueryListener);
                } else if (mediaQuery.removeListener) {
                    mediaQuery.removeListener(this.mediaQueryListener);
                }
            }

            // 新しいリスナーを設定
            this.mediaQueryListener = handleChange;
            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', handleChange);
            } else if (mediaQuery.addListener) {
                mediaQuery.addListener(handleChange);
            }
        } catch (error) {
            console.warn('Error setting up system theme watcher:', error);
        }
    }

    setupLivewireListeners() {
        // Livewireが利用可能になった時点でリスナーを設定
        const setupListeners = () => {
            if (typeof Livewire !== 'undefined' && !this._livewireListenersSetup) {
                try {
                    this._livewireListenersSetup = true;

                    Livewire.on('theme-changed', (event) => {
                        console.log('Livewire theme-changed event:', event);
                        let theme;
                        if (Array.isArray(event)) {
                            theme = event[0]?.theme || event[0];
                        } else {
                            theme = event.theme || event;
                        }
                        
                        if (theme && typeof theme === 'string') {
                            this.applyThemeToDOM(theme);
                        }
                    });

                    Livewire.on('appearance-updated', (event) => {
                        console.log('Livewire appearance-updated event:', event);
                        
                        let theme;
                        if (Array.isArray(event)) {
                            theme = event[0]?.theme;
                        } else {
                            theme = event.theme;
                        }

                        if (theme) {
                            this.applyThemeToDOM(theme);
                        } else {
                            // テーマが指定されていない場合は現在のテーマを再適用
                            this.applyThemeToDOM(this.currentTheme);
                        }
                    });

                    console.log('Livewire theme listeners setup completed');
                } catch (error) {
                    console.warn('Error setting up Livewire listeners:', error);
                    this._livewireListenersSetup = false;
                }
            }
        };

        // 複数のタイミングで試行
        const events = ['livewire:init', 'livewire:navigated'];
        events.forEach(eventName => {
            document.addEventListener(eventName, setupListeners);
        });
        
        // 即座に試行
        setupListeners();
        
        // 少し待ってから再試行
        setTimeout(setupListeners, 500);
        setTimeout(setupListeners, 1000);
    }

    setupNavigationListeners() {
        try {
            // Livewire navigate イベント
            document.addEventListener('livewire:navigate', () => {
                console.log('Livewire navigate - preserving theme');
            });

            // Livewire navigated イベント  
            document.addEventListener('livewire:navigated', () => {
                console.log('Livewire navigated - reapplying theme');
                setTimeout(() => {
                    this.applyThemeToDOM(this.currentTheme);
                }, 50);
            });

            // ページが表示されたときの処理
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    setTimeout(() => {
                        this.applyThemeToDOM(this.currentTheme);
                    }, 100);
                }
            });

            // ページフォーカス時の処理
            window.addEventListener('focus', () => {
                setTimeout(() => {
                    this.applyThemeToDOM(this.currentTheme);
                }, 100);
            });

            // 通常のページ遷移も監視
            window.addEventListener('beforeunload', () => {
                this.saveThemeToStorage(this.currentTheme);
            });
        } catch (error) {
            console.warn('Error setting up navigation listeners:', error);
        }
    }

    // オブザーバーパターンの実装
    addObserver(callback) {
        if (typeof callback === 'function') {
            this.observers.push(callback);
        }
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
        if (['light', 'dark', 'system'].includes(theme)) {
            console.log('Setting theme externally:', theme);
            this.applyThemeToDOM(theme);
            
            // Livewireコンポーネントが存在する場合は同期
            if (typeof Livewire !== 'undefined') {
                try {
                    Livewire.dispatch('theme-changed-externally', { theme });
                } catch (e) {
                    console.warn('Could not sync theme with Livewire:', e);
                }
            }
        } else {
            console.warn('Invalid theme:', theme);
        }
    }

    getCurrentTheme() {
        return this.currentTheme || this.getStoredTheme() || 'system';
    }

    getEffectiveTheme() {
        const theme = this.getCurrentTheme();
        if (theme === 'system') {
            return this.getSystemPreference() ? 'dark' : 'light';
        }
        return theme;
    }

    // 強制的にテーマを再適用
    forceReapply() {
        console.log('Force reapplying theme:', this.currentTheme);
        this.applyThemeToDOM(this.currentTheme);
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
        return this.initPromise || Promise.resolve();
    }

    // クリーンアップ
    destroy() {
        try {
            // メディアクエリリスナーを削除
            if (this.mediaQueryListener && window.matchMedia) {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                if (mediaQuery.removeEventListener) {
                    mediaQuery.removeEventListener('change', this.mediaQueryListener);
                } else if (mediaQuery.removeListener) {
                    mediaQuery.removeListener(this.mediaQueryListener);
                }
            }

            this.observers = [];
            this.pendingCallbacks = [];
            this._livewireListenersSetup = false;
            this.isInitialized = false;
            this.initPromise = null;
            this.mediaQueryListener = null;
        } catch (error) {
            console.warn('Error in ThemeManager cleanup:', error);
        }
    }
}

// ThemeManagerの初期化を安全に行う
let themeManagerInstance = null;

function initializeThemeManager() {
    if (!themeManagerInstance) {
        console.log('Initializing ThemeManager...');
        
        try {
            themeManagerInstance = new ThemeManager();
            
            // グローバルに利用可能にする
            window.ThemeManager = themeManagerInstance;
            
            // 便利なヘルパー関数（エラーハンドリング付き）
            window.setTheme = (theme) => {
                try {
                    if (window.ThemeManager) {
                        window.ThemeManager.setTheme(theme);
                        return true;
                    } else {
                        console.warn('ThemeManager not available, queuing theme setting');
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
                    if (window.ThemeManager) {
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
        } catch (error) {
            console.error('Failed to initialize ThemeManager:', error);
        }
    }
    
    return themeManagerInstance;
}

// 複数回の初期化を防ぐフラグ
if (!window._themeManagerInitialized) {
    window._themeManagerInitialized = true;
    
    try {
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
            if (window.ThemeManager) {
                window.ThemeManager.forceReapply();
            } else {
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
    } catch (error) {
        console.error('Critical error in ThemeManager initialization:', error);
    }
}

// テーマ適用の確認用
console.log('Enhanced Theme Manager script loaded and initialized');

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}