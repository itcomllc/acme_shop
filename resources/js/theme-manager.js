// Simple Theme Manager - Livewire driven
class SimpleThemeManager {
    constructor() {
        this.currentTheme = 'system';
        this.isInitialized = false;
        this.listenersSetup = false; // リスナー重複防止
        this.init();
    }

    init() {
        if (this.isInitialized) return;

        // 初期テーマを適用
        this.applyInitialTheme();
        
        // Livewireイベントリスナーを設定
        this.setupLivewireListeners();
        
        // システムテーマ変更を監視
        this.watchSystemTheme();
        
        this.isInitialized = true;
        console.log('Simple Theme Manager initialized');
    }

    applyInitialTheme() {
        try {
            // localStorageまたはmeta tagからテーマを取得
            let theme = localStorage.getItem('theme');
            
            if (!theme) {
                const metaTheme = document.querySelector('meta[name="theme"]');
                theme = metaTheme ? metaTheme.getAttribute('content') : 'system';
            }

            this.currentTheme = theme || 'system';
            this.applyTheme(this.currentTheme);
            
        } catch (error) {
            console.warn('Error applying initial theme:', error);
            this.applyTheme('system');
        }
    }

    applyTheme(theme) {
        try {
            const html = document.documentElement;
            const body = document.body;
            
            // 既存のテーマクラスを削除
            html.classList.remove('dark', 'light');
            if (body) {
                body.classList.remove('dark', 'light');
            }
            
            // data-theme属性を更新
            html.setAttribute('data-theme', theme);
            
            let effectiveTheme;
            
            if (theme === 'dark') {
                html.classList.add('dark');
                if (body) body.classList.add('dark');
                effectiveTheme = 'dark';
            } else if (theme === 'light') {
                html.classList.add('light');  
                if (body) body.classList.add('light');
                effectiveTheme = 'light';
            } else { // system
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) {
                    html.classList.add('dark');
                    if (body) body.classList.add('dark');
                    effectiveTheme = 'dark';
                } else {
                    html.classList.add('light');
                    if (body) body.classList.add('light');
                    effectiveTheme = 'light';
                }
            }
            
            this.currentTheme = theme;
            
            // localStorageに保存
            localStorage.setItem('theme', theme);
            
            console.log(`Theme applied: ${theme} (effective: ${effectiveTheme})`);
            
            // カスタムイベントを発火
            window.dispatchEvent(new CustomEvent('theme-applied', { 
                detail: { theme, effectiveTheme } 
            }));

        } catch (error) {
            console.error('Error applying theme:', error);
        }
    }

    setupLivewireListeners() {
        // 既にリスナーが設定済みの場合はスキップ
        if (this.listenersSetup) {
            console.log('Livewire listeners already setup, skipping');
            return;
        }

        // Livewireの初期化を待つ
        const setupListeners = () => {
            if (typeof Livewire !== 'undefined') {
                try {
                    // テーマ更新イベント
                    Livewire.on('theme-updated', (event) => {
                        const theme = Array.isArray(event) ? event[0]?.theme : event.theme;
                        if (theme) {
                            console.log('Received theme update from Livewire:', theme);
                            this.applyTheme(theme);
                        }
                    });

                    // 全体設定更新イベント
                    Livewire.on('appearance-updated', (event) => {
                        const data = Array.isArray(event) ? event[0] : event;
                        if (data?.theme) {
                            console.log('Received appearance update from Livewire:', data.theme);
                            this.applyTheme(data.theme);
                        }
                    });

                    this.listenersSetup = true;
                    console.log('Livewire theme listeners setup completed');
                } catch (error) {
                    console.error('Error setting up Livewire listeners:', error);
                }
            } else {
                // Livewireがまだ利用できない場合は少し待つ
                setTimeout(setupListeners, 100);
            }
        };

        // 一度だけ実行
        setupListeners();
    }

    watchSystemTheme() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            const handleChange = () => {
                if (this.currentTheme === 'system') {
                    console.log('System color scheme changed, reapplying system theme');
                    this.applyTheme('system');
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

    // 外部から呼び出し可能なメソッド
    setTheme(theme) {
        if (['light', 'dark', 'system'].includes(theme)) {
            this.applyTheme(theme);
            return true;
        }
        return false;
    }

    getCurrentTheme() {
        return this.currentTheme;
    }

    getEffectiveTheme() {
        if (this.currentTheme === 'system') {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return this.currentTheme;
    }
}

// ThemeManagerを初期化（重複防止）
let themeManager = null;

function initThemeManager() {
    if (!themeManager && !window._themeManagerInitialized) {
        window._themeManagerInitialized = true;
        themeManager = new SimpleThemeManager();
        
        // グローバルに利用可能にする
        window.ThemeManager = themeManager;
        window.setTheme = (theme) => themeManager.setTheme(theme);
        window.getCurrentTheme = () => themeManager.getCurrentTheme();
        window.getEffectiveTheme = () => themeManager.getEffectiveTheme();
        
        console.log('ThemeManager initialized and attached to window');
    } else if (themeManager) {
        console.log('ThemeManager already initialized');
    }
    return themeManager;
}

// 初期化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeManager);
} else {
    initThemeManager();
}

console.log('Simple Theme Manager script loaded');

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SimpleThemeManager;
}