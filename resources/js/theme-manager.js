// Theme Manager for SSL SaaS Platform - Enhanced Version with Error Handling
class ThemeManager {
    constructor() {
        this.currentTheme = 'system';
        this.isInitialized = false;
        this.init();
    }

    init() {
        // 即座にテーマを適用（ページ読み込み前）
        this.applyStoredThemeImmediately();
        
        // DOMContentLoadedでも再度確認
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.applyStoredTheme());
        } else {
            this.applyStoredTheme();
        }

        // システムテーマ変更の監視
        this.watchSystemTheme();
        
        // Livewireイベントのリスナー設定
        this.setupLivewireListeners();
        
        // ページ遷移時の処理（Livewire navigate）
        this.setupNavigationListeners();
        
        this.isInitialized = true;
        console.log('ThemeManager initialized');
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

        // サーバーサイドと同期（セッション更新）
        this.syncWithServer(theme);

        // カスタムイベントを発火
        window.dispatchEvent(new CustomEvent('theme-applied', { 
            detail: { theme, effectiveTheme } 
        }));
    }

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
            
            mediaQuery.addEventListener('change', (e) => {
                const currentTheme = this.getCurrentTheme();
                if (currentTheme === 'system') {
                    console.log('System color scheme changed, reapplying theme');
                    this.applyThemeToDOM('system');
                }
            });
        }
    }

    setupLivewireListeners() {
        // Livewireが利用可能になった時点でリスナーを設定
        document.addEventListener('livewire:init', () => {
            console.log('Setting up Livewire theme listeners');
            
            if (typeof Livewire !== 'undefined') {
                try {
                    Livewire.on('theme-changed', (event) => {
                        console.log('Livewire theme-changed event:', event);
                        const theme = Array.isArray(event) ? event[0]?.theme : event.theme;
                        if (theme) {
                            this.applyThemeToDOM(theme);
                        }
                    });

                    Livewire.on('appearance-updated', () => {
                        console.log('Livewire appearance-updated event');
                        // 現在のテーマを再適用
                        this.applyThemeToDOM(this.currentTheme);
                    });
                } catch (error) {
                    console.warn('Error setting up Livewire listeners:', error);
                }
            }
        });

        // Livewireが既に初期化されている場合
        if (typeof Livewire !== 'undefined') {
            this.setupLivewireEvents();
        }
    }

    setupLivewireEvents() {
        try {
            Livewire.on('theme-changed', (event) => {
                console.log('Livewire theme-changed event:', event);
                const theme = Array.isArray(event) ? event[0]?.theme : event.theme;
                if (theme) {
                    this.applyThemeToDOM(theme);
                }
            });

            Livewire.on('appearance-updated', () => {
                console.log('Livewire appearance-updated event');
                this.applyThemeToDOM(this.currentTheme);
            });
        } catch (error) {
            console.log('Error setting up Livewire events:', error);
        }
    }

    setupNavigationListeners() {
        // Livewire navigate イベント
        document.addEventListener('livewire:navigate', () => {
            console.log('Livewire navigate - reapplying theme');
            // ナビゲーション後にテーマを再適用
            setTimeout(() => {
                this.applyThemeToDOM(this.currentTheme);
            }, 50);
        });

        // Livewire navigated イベント  
        document.addEventListener('livewire:navigated', () => {
            console.log('Livewire navigated - reapplying theme');
            this.applyThemeToDOM(this.currentTheme);
        });

        // 通常のページ遷移も監視
        window.addEventListener('beforeunload', () => {
            // ページ遷移前にテーマを保存
            localStorage.setItem('theme', this.currentTheme);
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
        console.log('Force reapplying theme:', this.currentTheme);
        this.applyThemeToDOM(this.currentTheme);
    }

    // 初期化チェック用メソッド
    waitForInitialization(callback, maxWait = 5000) {
        const startTime = Date.now();
        const checkInterval = setInterval(() => {
            if (this.isInitialized) {
                clearInterval(checkInterval);
                callback();
            } else if (Date.now() - startTime > maxWait) {
                clearInterval(checkInterval);
                console.warn('ThemeManager initialization timeout');
                callback();
            }
        }, 50);
    }
}

// ThemeManagerの初期化を安全に行う
let themeManagerInstance = null;

function initializeThemeManager() {
    if (!themeManagerInstance) {
        themeManagerInstance = new ThemeManager();
        
        // グローバルに利用可能にする
        window.ThemeManager = themeManagerInstance;
        
        // 便利なヘルパー関数（エラーハンドリング付き）
        window.setTheme = (theme) => {
            try {
                if (window.ThemeManager) {
                    window.ThemeManager.setTheme(theme);
                } else {
                    console.warn('ThemeManager not available, queuing theme setting');
                    // ThemeManagerが利用可能になるまで待機
                    setTimeout(() => {
                        if (window.ThemeManager) {
                            window.ThemeManager.setTheme(theme);
                        }
                    }, 100);
                }
            } catch (error) {
                console.error('Error setting theme:', error);
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
                }
            } catch (error) {
                console.error('Error force reapplying theme:', error);
            }
        };
    }
    
    return themeManagerInstance;
}

// 即座に初期化を開始
initializeThemeManager();

// DOMContentLoadedでも確実に初期化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeThemeManager);
} else {
    initializeThemeManager();
}

// ページ読み込み完了後にも再確認
window.addEventListener('load', () => {
    console.log('Page fully loaded, ensuring theme manager is ready');
    if (window.ThemeManager) {
        window.ThemeManager.forceReapply();
    } else {
        initializeThemeManager();
    }
});

// テーマ適用の確認用
console.log('Theme Manager script loaded');