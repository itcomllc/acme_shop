<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\{Auth, Log, Session};
use Livewire\Component;

class Appearance extends Component
{
    public string $theme = 'system';
    public string $language = 'en';
    public string $timezone = 'UTC';
    public bool $animations = true;
    public bool $sound_notifications = false;

    public function mount(): void
    {
        try {
            // セッションから設定を取得
            $this->theme = session('theme', 'system');
            $this->language = session('locale', app()->getLocale());
            
            // ユーザーから設定を取得
            $user = Auth::user();
            if ($user) {
                $this->timezone = $user->timezone ?? config('app.timezone', 'UTC');
                
                // ユーザーのpreferencesから取得
                if ($user->preferences) {
                    $this->theme = $user->preferences['theme'] ?? $this->theme;
                    $this->language = $user->preferences['language'] ?? $this->language;
                    $this->animations = $user->preferences['animations'] ?? true;
                    $this->sound_notifications = $user->preferences['sound_notifications'] ?? false;
                }
            } else {
                $this->timezone = config('app.timezone', 'UTC');
            }
            
            Log::info('Appearance component mounted', [
                'theme' => $this->theme,
                'language' => $this->language,
                'timezone' => $this->timezone
            ]);
            
            // マウント時にもテーマを確実にフロントエンドに送信
            $this->dispatchThemeUpdate($this->theme, 'mount');
            
        } catch (\Exception $e) {
            Log::error('Error mounting Appearance component', [
                'error' => $e->getMessage()
            ]);
            
            // デフォルト値を設定
            $this->theme = 'system';
            $this->language = 'en';
            $this->timezone = 'UTC';
            $this->animations = true;
            $this->sound_notifications = false;
        }
    }

    /**
     * テーマ更新のdispatch（確実にフロントエンドに送る）
     */
    private function dispatchThemeUpdate(string $theme, string $source = 'unknown'): void
    {
        try {
            // 複数の方法でフロントエンドにテーマを送信
            
            // 1. Livewireイベント
            $this->dispatch('theme-updated', theme: $theme, source: $source);
            
            // 2. JavaScript実行（より確実）
            $this->js("
                console.log('🎨 Dispatching theme from Livewire:', '$theme', '($source)');
                
                // テーマを直接適用
                if (window.setTheme) {
                    window.setTheme('$theme');
                } else {
                    // setTheme関数がない場合の直接適用
                    const html = document.documentElement;
                    const body = document.body;
                    
                    html.classList.remove('dark', 'light');
                    if (body) body.classList.remove('dark', 'light');
                    
                    if ('$theme' === 'dark') {
                        html.classList.add('dark');
                        if (body) body.classList.add('dark');
                    } else if ('$theme' === 'light') {
                        html.classList.add('light');
                        if (body) body.classList.add('light');
                    } else {
                        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                        if (prefersDark) {
                            html.classList.add('dark');
                            if (body) body.classList.add('dark');
                        } else {
                            html.classList.add('light');
                            if (body) body.classList.add('light');
                        }
                    }
                    
                    localStorage.setItem('theme', '$theme');
                }
                
                // カスタムイベントも発火
                window.dispatchEvent(new CustomEvent('livewire-theme-updated', { 
                    detail: { theme: '$theme', source: '$source' } 
                }));
            ");
            
            Log::info('Theme update dispatched', [
                'theme' => $theme,
                'source' => $source
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error dispatching theme update', [
                'theme' => $theme,
                'source' => $source,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * テーマ変更時の処理（ライブ更新）
     */
    public function updatedTheme($value): void
    {
        try {
            if (!in_array($value, ['light', 'dark', 'system'])) {
                $value = 'system';
                $this->theme = $value;
                return;
            }

            Log::info('Theme updated via live update', ['theme' => $value]);

            // セッションに保存
            session(['theme' => $value]);
            
            // ユーザーのpreferencesにも保存
            $user = Auth::user();
            if ($user) {
                $preferences = $user->preferences ?? [];
                $preferences['theme'] = $value;
                $user->update(['preferences' => $preferences]);
            }
            
            // フロントエンドに確実にテーマを送信
            $this->dispatchThemeUpdate($value, 'live-update');
            
        } catch (\Exception $e) {
            Log::error('Error updating theme', [
                'theme' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 言語変更時の処理
     */
    public function updatedLanguage($value): void
    {
        try {
            session(['locale' => $value]);
            app()->setLocale($value);
            
            $user = Auth::user();
            if ($user) {
                $preferences = $user->preferences ?? [];
                $preferences['language'] = $value;
                $user->update(['preferences' => $preferences]);
            }
            
            Log::info('Language updated', ['language' => $value]);
            
        } catch (\Exception $e) {
            Log::error('Error updating language', [
                'language' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * タイムゾーン変更時の処理
     */
    public function updatedTimezone($value): void
    {
        try {
            if (in_array($value, timezone_identifiers_list())) {
                $user = Auth::user();
                if ($user) {
                    $user->update(['timezone' => $value]);
                    Log::info('Timezone updated successfully', ['timezone' => $value]);
                } else {
                    Log::warning('No authenticated user for timezone update');
                }
            } else {
                Log::warning('Invalid timezone provided', ['timezone' => $value]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating timezone', [
                'timezone' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * フォーム送信処理
     */
    public function updateAppearance(): void
    {
        try {
            Log::info('updateAppearance called', [
                'theme' => $this->theme,
                'language' => $this->language,
                'timezone' => $this->timezone,
                'animations' => $this->animations,
                'sound_notifications' => $this->sound_notifications
            ]);

            // バリデーション
            if (!in_array($this->theme, ['light', 'dark', 'system'])) {
                $this->theme = 'system';
            }

            if (!in_array($this->timezone, timezone_identifiers_list())) {
                $this->timezone = config('app.timezone', 'UTC');
            }

            // セッションに全て保存
            session([
                'theme' => $this->theme,
                'locale' => $this->language,
                'animations' => $this->animations,
                'sound_notifications' => $this->sound_notifications,
            ]);

            // アプリケーションロケールを設定
            app()->setLocale($this->language);

            // ユーザー設定を更新
            $user = Auth::user();
            if ($user) {
                // タイムゾーンを更新
                $user->update(['timezone' => $this->timezone]);

                // preferencesも更新
                $preferences = [
                    'theme' => $this->theme,
                    'language' => $this->language,
                    'animations' => $this->animations,
                    'sound_notifications' => $this->sound_notifications,
                ];
                
                $user->update(['preferences' => $preferences]);
            }

            // フロントエンドに全設定を通知（最重要）
            $this->dispatchThemeUpdate($this->theme, 'form-submit');
            
            $this->dispatch('appearance-updated', 
                theme: $this->theme,
                language: $this->language,
                timezone: $this->timezone,
                animations: $this->animations,
                sound_notifications: $this->sound_notifications
            );

            // 成功メッセージ
            session()->flash('status', 'appearance-updated');
            
            Log::info('Appearance settings updated successfully');
            
        } catch (\Exception $e) {
            Log::error('Error updating appearance settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            session()->flash('error', 'Failed to update appearance settings');
        }
    }

    /**
     * デフォルト設定にリセット
     */
    public function resetToDefaults(): void
    {
        try {
            $this->theme = 'system';
            $this->language = 'en';
            $this->timezone = config('app.timezone', 'UTC');
            $this->animations = true;
            $this->sound_notifications = false;

            // リセット後も確実にテーマを送信
            $this->dispatchThemeUpdate($this->theme, 'reset');
            
            $this->updateAppearance();
            
        } catch (\Exception $e) {
            Log::error('Error resetting appearance settings', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 利用可能なタイムゾーン一覧を取得
     */
    public function getAvailableTimezones(): array
    {
        return [
            'UTC' => 'UTC (Coordinated Universal Time)',
            'America/New_York' => 'Eastern Time (US & Canada)',
            'America/Chicago' => 'Central Time (US & Canada)',
            'America/Denver' => 'Mountain Time (US & Canada)',
            'America/Los_Angeles' => 'Pacific Time (US & Canada)',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Europe/Berlin' => 'Berlin',
            'Europe/Rome' => 'Rome',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Shanghai',
            'Asia/Seoul' => 'Seoul',
            'Asia/Kolkata' => 'Mumbai, Kolkata',
            'Asia/Dubai' => 'Dubai',
            'Australia/Sydney' => 'Sydney',
            'Australia/Melbourne' => 'Melbourne',
            'Pacific/Auckland' => 'Auckland',
        ];
    }

    /**
     * 利用可能な言語一覧を取得
     */
    public function getAvailableLanguages(): array
    {
        return [
            'en' => 'English',
            'ja' => '日本語 (Japanese)',
            'es' => 'Español (Spanish)', 
            'fr' => 'Français (French)',
            'de' => 'Deutsch (German)',
            'zh' => '中文 (Chinese)',
            'ko' => '한국어 (Korean)',
            'pt' => 'Português (Portuguese)',
            'it' => 'Italiano (Italian)',
            'ru' => 'Русский (Russian)',
        ];
    }

    public function render()
    {
        return view('livewire.settings.appearance', [
            'availableTimezones' => $this->getAvailableTimezones(),
            'availableLanguages' => $this->getAvailableLanguages(),
        ]);
    }
}