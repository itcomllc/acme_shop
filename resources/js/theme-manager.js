// シンプルで確実なテーママネージャー
(function() {
    console.log('Simple Theme Manager loading...');

    // テーマ適用関数
    function applyTheme(theme) {
        try {
            const html = document.documentElement;
            const body = document.body;
            
            console.log('Applying theme:', theme);
            
            // 既存のテーマクラスを削除
            html.classList.remove('dark', 'light');
            if (body) {
                body.classList.remove('dark', 'light');
            }
            
            // data-theme属性を設定
            html.setAttribute('data-theme', theme);
            if (body) {
                body.setAttribute('data-theme', theme);
            }
            
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
            
            // localStorageに保存
            localStorage.setItem('theme', theme);
            
            console.log(`Theme applied: ${theme} (effective: ${effectiveTheme})`);
            console.log('HTML classes:', html.className);
            console.log('Body classes:', body ? body.className : 'no body');
            
            return effectiveTheme;
            
        } catch (error) {
            console.error('Error applying theme:', error);
        }
    }

    // テーマロード関数
    function loadTheme() {
        try {
            // 複数のソースからテーマを取得
            const localTheme = localStorage.getItem('theme');
            const bodyTheme = document.body ? document.body.dataset.theme : null;
            const metaTheme = document.querySelector('meta[name="theme"]')?.content;
            
            // 優先順位: localStorage > body data > meta > system
            const finalTheme = localTheme || bodyTheme || metaTheme || 'system';
            
            console.log('Loading theme sources:', {
                localStorage: localTheme,
                bodyData: bodyTheme,
                meta: metaTheme,
                final: finalTheme
            });
            
            applyTheme(finalTheme);
            return finalTheme;
            
        } catch (error) {
            console.error('Error loading theme:', error);
            applyTheme('system');
            return 'system';
        }
    }

    // サーバー同期
    function syncWithServer(theme) {
        if (typeof window.axios !== 'undefined') {
            window.axios.post('/api/user/preferences', { theme }).catch(err => {
                console.warn('Server sync failed:', err);
            });
        }
    }

    // 初期化
    function init() {
        console.log('Theme Manager initializing...');
        
        // 即座にテーマをロード
        const currentTheme = loadTheme();
        
        // Livewireイベントリスナー
        document.addEventListener('livewire:init', function() {
            if (typeof Livewire !== 'undefined') {
                Livewire.on('theme-updated', function(data) {
                    const theme = Array.isArray(data) ? data[0]?.theme : data.theme;
                    if (theme) {
                        console.log('Livewire theme update:', theme);
                        applyTheme(theme);
                    }
                });

                Livewire.on('appearance-updated', function(data) {
                    const eventData = Array.isArray(data) ? data[0] : data;
                    if (eventData?.theme) {
                        console.log('Livewire appearance update:', eventData.theme);
                        applyTheme(eventData.theme);
                    }
                });
            }
        });

        // ナビゲーションイベント
        document.addEventListener('livewire:navigated', function() {
            console.log('Livewire navigated - reloading theme');
            setTimeout(loadTheme, 10);
        });

        // ページ表示イベント
        window.addEventListener('pageshow', function() {
            console.log('Page show - reloading theme');
            setTimeout(loadTheme, 10);
        });

        // システムテーマ変更
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handleChange = () => {
                const currentTheme = localStorage.getItem('theme');
                if (!currentTheme || currentTheme === 'system') {
                    console.log('System theme changed');
                    applyTheme('system');
                }
            };

            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', handleChange);
            } else {
                mediaQuery.addListener(handleChange);
            }
        }

        console.log('Theme Manager initialized');
    }

    // グローバル関数
    window.setTheme = function(theme) {
        if (['light', 'dark', 'system'].includes(theme)) {
            console.log('Setting theme via global function:', theme);
            applyTheme(theme);
            syncWithServer(theme);
            return true;
        }
        return false;
    };

    window.toggleTheme = function() {
        const html = document.documentElement;
        const isDark = html.classList.contains('dark');
        const newTheme = isDark ? 'light' : 'dark';
        window.setTheme(newTheme);
        return newTheme;
    };

    window.getCurrentTheme = function() {
        return localStorage.getItem('theme') || 'system';
    };

    window.reloadTheme = function() {
        console.log('Manually reloading theme');
        loadTheme();
    };

    // Quick toggle (layout用)
    window.quickToggleTheme = window.toggleTheme;

    // 初期化実行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // さらに確実にするため、少し後にも実行
    setTimeout(init, 100);

    console.log('Simple Theme Manager script loaded');
})();