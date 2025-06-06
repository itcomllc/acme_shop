// Theme Manager for SSL SaaS Platform
class ThemeManager {
    constructor() {
        this.init();
    }

    init() {
        // DOMContentLoadedでテーマを適用
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.applyStoredTheme());
        } else {
            this.applyStoredTheme();
        }

        // システムテーマ変更の監視
        this.watchSystemTheme();
        
        // Livewireイベントのリスナー設定
        this.setupLivewireListeners();
    }

    applyStoredTheme() {
        try {
            // localStorageまたはサーバーサイドのsessionから取得
            let storedTheme = localStorage.getItem('theme');
            
            // localStorageにない場合は、サーバーサイドのセッションから取得
            if (!storedTheme) {
                // PHPセッションの値をメタタグから取得
                const metaTheme = document.querySelector('meta[name="theme"]');
                if (metaTheme) {
                    storedTheme = metaTheme.getAttribute('content');
                }
            }

            const theme = storedTheme || 'system';
            console.log('Applying stored theme:', theme);
            this.applyTheme(theme);
        } catch (error) {
            console.warn('Error applying stored theme:', error);
            this.applyTheme('system');
        }
    }

    applyTheme(theme) {
        const html = document.documentElement;
        const body = document.body;
        
        console.log('ThemeManager: Applying theme:', theme);
        
        // 既存のテーマクラスを削除
        html.classList.remove('dark', 'light');
        body.classList.remove('dark', 'light');
        
        // data-theme属性も更新
        html.setAttribute('data-theme', theme);
        
        if (theme === 'dark') {
            html.classList.add('dark');
            body.classList.add('dark');
            console.log('Dark theme applied');
        } else if (theme === 'light') {
            html.classList.add('light');  
            body.classList.add('light');
            console.log('Light theme applied');
        } else if (theme === 'system') {
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (prefersDark) {
                html.classList.add('dark');
                body.classList.add('dark');
                console.log('System dark theme applied');
            } else {
                html.classList.add('light');
                body.classList.add('light');
                console.log('System light theme applied');
            }
        }
        
        // localStorageに保存
        try {
            localStorage.setItem('theme', theme);
        } catch (e) {
            console.warn('Could not save theme to localStorage:', e);
        }

        // カスタムイベントを発火
        window.dispatchEvent(new CustomEvent('theme-applied', { 
            detail: { theme } 
        }));
    }

    watchSystemTheme() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            mediaQuery.addEventListener('change', (e) => {
                const currentTheme = localStorage.getItem('theme') || 'system';
                if (currentTheme === 'system') {
                    console.log('System color scheme changed, reapplying theme');
                    this.applyTheme('system');
                }
            });
        }
    }

    setupLivewireListeners() {
        // Livewireが利用可能な場合のみセットアップ
        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:init', () => {
                Livewire.on('theme-changed', (event) => {
                    console.log('Livewire theme-changed event:', event);
                    this.applyTheme(event.theme || event[0]?.theme);
                });

                Livewire.on('appearance-updated', () => {
                    // 最新のテーマ値を取得して適用
                    const themeInput = document.querySelector('input[name="theme"]:checked');
                    if (themeInput) {
                        this.applyTheme(themeInput.value);
                    }
                });
            });
        }
    }

    // 外部から呼び出し可能なメソッド
    setTheme(theme) {
        if (['light', 'dark', 'system'].includes(theme)) {
            this.applyTheme(theme);
            
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
        return localStorage.getItem('theme') || 'system';
    }

    getEffectiveTheme() {
        const theme = this.getCurrentTheme();
        if (theme === 'system') {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return theme;
    }
}

// グローバルに利用可能にする
window.ThemeManager = new ThemeManager();

// 便利なヘルパー関数
window.setTheme = (theme) => window.ThemeManager.setTheme(theme);
window.getCurrentTheme = () => window.ThemeManager.getCurrentTheme();
window.getEffectiveTheme = () => window.ThemeManager.getEffectiveTheme();

// テーマ適用の確認用
console.log('Theme Manager initialized. Current effective theme:', window.getEffectiveTheme());
