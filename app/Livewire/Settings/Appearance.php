<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\{Auth, Log, Cache};
use Livewire\Component;
use Carbon\Carbon;

class Appearance extends Component
{
    public string $theme = 'system';
    public string $language = 'en';
    public string $timezone = 'UTC';
    public bool $animations = true;
    public bool $sound_notifications = false;

    protected $listeners = [
        'theme-changed-externally' => 'handleExternalThemeChange'
    ];

    public function mount(): void
    {
        try {
            // Load user preferences from session or database
            $this->theme = session('theme', 'system');
            $this->language = session('locale', app()->getLocale());
            
            $user = Auth::user();
            $this->timezone = $user->timezone ?? config('app.timezone', 'UTC');
            
            // Load other preferences from session
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
            
            // Set safe defaults
            $this->theme = 'system';
            $this->language = 'en';
            $this->timezone = 'UTC';
            $this->animations = true;
            $this->sound_notifications = false;
        }
    }

    /**
     * テーマ変更時にリアルタイムで適用
     */
    public function updatedTheme($value): void
    {
        try {
            Log::info('Theme updated in Livewire', ['theme' => $value]);
            
            // 有効な値かチェック
            if (!in_array($value, ['light', 'dark', 'system'])) {
                $value = 'system';
            }

            // セッションに即座に保存
            session(['theme' => $value]);
            
            // ユーザーのデータベースにも保存（オプション）
            $user = Auth::user();
            if ($user && method_exists($user, 'update')) {
                try {
                    // カラムが存在する場合のみ更新
                    if (\Schema::hasColumn('users', 'theme_preference')) {
                        $user->update(['theme_preference' => $value]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not save theme to user table', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // JavaScriptのThemeManagerに通知
            $this->dispatch('theme-changed', theme: $value);
            
            Log::info('Theme change dispatched', ['theme' => $value]);
        } catch (\Exception $e) {
            Log::error('Error updating theme', [
                'theme' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 外部からのテーマ変更を処理
     */
    public function handleExternalThemeChange($data): void
    {
        try {
            $theme = $data['theme'] ?? 'system';
            Log::info('External theme change received', ['theme' => $theme]);
            
            if (in_array($theme, ['light', 'dark', 'system'])) {
                $this->theme = $theme;
                session(['theme' => $theme]);
            }
        } catch (\Exception $e) {
            Log::error('Error handling external theme change', [
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
            Log::info('Language updated', ['language' => $value]);
            
            // セッションに保存
            session(['locale' => $value]);
            
            // アプリケーションロケールを設定
            app()->setLocale($value);
            
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
            Log::info('Timezone updated', ['timezone' => $value]);
            
            // 有効なタイムゾーンかチェック
            if (in_array($value, timezone_identifiers_list())) {
                $user = Auth::user();
                if ($user) {
                    $user->update(['timezone' => $value]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error updating timezone', [
                'timezone' => $value,
                'error' => $e->getMessage()
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
     * 全設定を一度に更新
     */
    public function updateAppearance(): void
    {
        try {
            Log::info('Updating all appearance settings', [
                'theme' => $this->theme,
                'language' => $this->language,
                'timezone' => $this->timezone,
                'animations' => $this->animations,
                'sound_notifications' => $this->sound_notifications
            ]);

            // 全設定をセッションに保存
            session([
                'theme' => $this->theme,
                'locale' => $this->language,
                'animations' => $this->animations,
                'sound_notifications' => $this->sound_notifications,
            ]);

            // アプリケーションロケールを設定
            app()->setLocale($this->language);

            // ユーザーのタイムゾーンを更新
            $user = Auth::user();
            if ($user && in_array($this->timezone, timezone_identifiers_list())) {
                $user->update(['timezone' => $this->timezone]);
            }

            // すべての変更をフロントエンドに通知
            $this->dispatch('appearance-updated', [
                'theme' => $this->theme,
                'language' => $this->language,
                'timezone' => $this->timezone,
                'animations' => $this->animations,
                'sound_notifications' => $this->sound_notifications
            ]);

            // 成功メッセージを設定
            session()->flash('status', 'appearance-updated');
            
            Log::info('Appearance settings updated successfully');
            
        } catch (\Exception $e) {
            Log::error('Error updating appearance settings', [
                'error' => $e->getMessage()
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
            Log::info('Resetting appearance settings to defaults');
            
            $this->theme = 'system';
            $this->language = 'en';
            $this->timezone = config('app.timezone', 'UTC');
            $this->animations = true;
            $this->sound_notifications = false;

            $this->updateAppearance();
            
            Log::info('Appearance settings reset to defaults');
            
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

    /**
     * レンダリング
     */
    public function render()
    {
        /** @var \Livewire\Component $view */
        $view = view('livewire.settings.appearance', [
            'availableTimezones' => $this->getAvailableTimezones(),
            'availableLanguages' => $this->getAvailableLanguages(),
        ]);
        return $view->extends('layouts.app');
    }
}