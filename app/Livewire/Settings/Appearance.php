<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\{Auth, Log, Session, Schema};
use Livewire\Component;

class Appearance extends Component
{
    public string $theme = 'system';
    public string $language = 'en';
    public string $timezone = 'UTC';
    public bool $animations = true;
    public bool $sound_notifications = false;

    private $lastThemeUpdate = null; // デバウンス用

    public function mount(): void
    {
        try {
            // セッションから設定を取得
            $this->theme = session('theme', 'system');
            $this->language = session('locale', app()->getLocale());
            
            // ユーザーから設定を取得
            $user = Auth::user();
            if ($user) {
                $this->timezone = $user->getTimezone();
            } else {
                $this->timezone = config('app.timezone', 'UTC');
            }
            
            // その他の設定
            $this->animations = session('animations', true);
            $this->sound_notifications = session('sound_notifications', false);

            Log::info('Appearance component mounted', [
                'theme' => $this->theme,
                'language' => $this->language,
                'timezone' => $this->timezone
            ]);
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
     * テーマ変更時の処理（デバウンス機能付き）
     */
    public function updatedTheme($value): void
    {
        try {
            if (!in_array($value, ['light', 'dark', 'system'])) {
                $value = 'system';
            }

            // デバウンス処理
            $now = microtime(true);
            if ($this->lastThemeUpdate && ($now - $this->lastThemeUpdate) < 0.5) {
                Log::debug('Theme update throttled', ['value' => $value]);
                return;
            }
            $this->lastThemeUpdate = $now;

            // セッションに保存
            session(['theme' => $value]);
            
            // JavaScriptに通知（配列形式で送信）
            $this->dispatch('theme-changed', theme: $value);
            
            Log::info('Theme updated', ['theme' => $value, 'session_id' => session()->getId()]);
            
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
            
            $this->dispatch('language-updated', language: $value);
            
            Log::info('Language updated', ['language' => $value]);
            
        } catch (\Exception $e) {
            Log::error('Error updating language', [
                'language' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * タイムゾーン変更時の処理（修正版）
     */
    public function updatedTimezone($value): void
    {
        try {
            if (in_array($value, timezone_identifiers_list())) {
                $user = Auth::user();
                if ($user) {
                    Log::info('Before timezone update', [
                        'user_id' => $user->id,
                        'current_timezone' => $user->timezone,
                        'new_timezone' => $value,
                        'fillable' => $user->getFillable()
                    ]);

                    // 直接updateメソッドを使用
                    $updated = $user->update(['timezone' => $value]);
                    
                    Log::info('Timezone update result', [
                        'updated' => $updated,
                        'user_timezone_after' => $user->fresh()->timezone
                    ]);

                    if ($updated) {
                        $this->dispatch('timezone-updated', timezone: $value);
                        Log::info('Timezone updated successfully', ['timezone' => $value]);
                    } else {
                        Log::warning('Timezone update failed');
                    }
                } else {
                    Log::warning('No authenticated user for timezone update');
                }
            } else {
                Log::warning('Invalid timezone provided', ['timezone' => $value]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating timezone', [
                'timezone' => $value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * アニメーション設定変更時の処理
     */
    public function updatedAnimations($value): void
    {
        try {
            session(['animations' => $value]);
            $this->dispatch('animations-updated', animations: $value);
        } catch (\Exception $e) {
            Log::error('Error updating animations setting', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 音声通知設定変更時の処理
     */
    public function updatedSoundNotifications($value): void
    {
        try {
            session(['sound_notifications' => $value]);
            $this->dispatch('sound-notifications-updated', soundNotifications: $value);
        } catch (\Exception $e) {
            Log::error('Error updating sound notifications setting', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * フォーム送信処理（修正版）
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

            // ユーザーのタイムゾーンを更新（確実に更新）
            $user = Auth::user();
            if ($user) {
                Log::info('Before appearance update - user timezone', [
                    'user_id' => $user->id,
                    'current_timezone' => $user->timezone,
                    'new_timezone' => $this->timezone
                ]);

                // fillableに確実にtimezoneが含まれていることを確認
                if (!in_array('timezone', $user->getFillable())) {
                    Log::error('timezone is not in fillable array', [
                        'fillable' => $user->getFillable()
                    ]);
                }

                // 強制的にtimezone属性を設定
                $user->timezone = $this->timezone;
                $saved = $user->save();

                Log::info('Timezone save result', [
                    'saved' => $saved,
                    'user_timezone_after_save' => $user->timezone,
                    'fresh_user_timezone' => $user->fresh()->timezone
                ]);

                // preferencesも更新
                $user->updatePreferences([
                    'theme' => $this->theme,
                    'language' => $this->language,
                    'animations' => $this->animations,
                    'sound_notifications' => $this->sound_notifications,
                ]);
            }

            // サーバーサイドとの同期もここで行う
            $this->syncPreferencesWithServer();

            // フロントエンドに通知（Livewire 3の名前付きパラメータ形式）
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
     * サーバーAPIとの同期
     */
    private function syncPreferencesWithServer(): void
    {
        try {
            // 内部API呼び出しでセッションとの同期
            $preferences = [
                'theme' => $this->theme,
                'language' => $this->language,
                'timezone' => $this->timezone,
            ];

            // HTTPクライアントを使用してAPIと同期
            $response = \Http::withHeaders([
                'X-CSRF-TOKEN' => csrf_token(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(url('/api/user/preferences'), $preferences);

            if (!$response->successful()) {
                Log::warning('Failed to sync preferences with server', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Error syncing preferences with server', [
                'error' => $e->getMessage()
            ]);
            // サーバー同期の失敗は致命的ではないので続行
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

            $this->updateAppearance();
            
        } catch (\Exception $e) {
            Log::error('Error resetting appearance settings', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 外部からのテーマ変更を処理（リスナーではなく直接呼び出し用）
     */
    public function handleExternalThemeChange($theme): void
    {
        try {
            Log::info('External theme change received', ['theme' => $theme]);
            
            if (in_array($theme, ['light', 'dark', 'system'])) {
                $this->theme = $theme;
                session(['theme' => $theme]);
                
                // フロントエンドに通知
                $this->dispatch('theme-changed', theme: $theme);
            }
        } catch (\Exception $e) {
            Log::error('Error handling external theme change', [
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