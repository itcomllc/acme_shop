import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF Token setup
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

// Laravel Echo setup for real-time features (Pusherキーが設定されている場合のみ)
const pusherAppKey = import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;

if (pusherAppKey && pusherAppKey !== '' && pusherAppKey !== 'your-pusher-key') {
    try {
        // 動的にPusherを読み込む
        import('pusher-js').then((PusherModule) => {
            const Pusher = PusherModule.default;
            window.Pusher = Pusher;

            // Laravel Echoを動的に読み込む
            import('laravel-echo').then((EchoModule) => {
                const Echo = EchoModule.default;

                window.Echo = new Echo({
                    broadcaster: 'pusher',
                    key: pusherAppKey,
                    cluster: pusherCluster || 'mt1',
                    wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${pusherCluster || 'mt1'}.pusher.app`,
                    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
                    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
                    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
                    enabledTransports: ['ws', 'wss'],
                });

                console.log('Laravel Echo initialized with Pusher');
            }).catch(error => {
                console.warn('Failed to load Laravel Echo:', error);
            });
        }).catch(error => {
            console.warn('Failed to load Pusher:', error);
        });
    } catch (error) {
        console.warn('Error setting up Pusher/Echo:', error);
    }
} else {
    console.log('Pusher not configured - real-time features disabled');
    
    // Pusherなしでの完全なフォールバック実装（Livewireエラーを回避）
    window.Echo = {
        channel: () => ({
            listen: () => {},
            stopListening: () => {},
            whisper: () => {},
            stopWhispering: () => {}
        }),
        private: () => ({
            listen: () => {},
            stopListening: () => {},
            whisper: () => {},
            stopWhispering: () => {}
        }),
        join: () => ({
            listen: () => {},
            stopListening: () => {},
            here: () => {},
            joining: () => {},
            leaving: () => {},
            whisper: () => {},
            stopWhispering: () => {}
        }),
        disconnect: () => {},
        // Livewireが期待するsocketIdメソッドを追加
        socketId: () => null,
        // その他のLivewireが期待する可能性があるメソッド
        connector: {
            socket: {
                id: null
            }
        }
    };
    
    // Echoのsocket情報を模擬
    Object.defineProperty(window.Echo, 'socketId', {
        value: () => null,
        writable: false,
        enumerable: true,
        configurable: false
    });
}