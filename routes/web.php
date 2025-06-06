<?php

use Illuminate\Support\Facades\{Route, Auth, Log, Schema};

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Settings Routes
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // Livewireコンポーネントを正しい方法でルーティング
    Route::get('settings/profile', function () {
        return view('livewire.settings.profile-wrapper');
    })->name('settings.profile');
    
    Route::get('settings/password', function () {
        return view('livewire.settings.password-wrapper');
    })->name('settings.password');
    
    Route::get('settings/appearance', function () {
        return view('livewire.settings.appearance-wrapper');
    })->name('settings.appearance');

    // テーマAPI - 改善版（エラーハンドリング強化）
    Route::post('/api/user/preferences', function (Illuminate\Http\Request $request) {
        $request->validate([
            'theme' => 'required|in:light,dark,system',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        try {
            $response = [
                'success' => true,
                'theme' => $request->theme,
                'language' => $request->get('language', app()->getLocale()),
                'timezone' => $request->get('timezone'),
                'message' => 'Preferences updated successfully'
            ];

            // セッションに保存
            session(['theme' => $request->theme]);
            
            if ($request->has('language')) {
                session(['locale' => $request->language]);
                app()->setLocale($request->language);
                $response['language'] = $request->language;
            }

            // ユーザーがログインしている場合、データベースにも保存
            if (Auth::check()) {
                try {
                    $user = Auth::user();
                    $updateData = [];
                    
                    // テーマ設定（カラムが存在する場合）
                    if (Schema::hasColumn('users', 'theme_preference')) {
                        $updateData['theme_preference'] = $request->theme;
                    }
                    
                    // タイムゾーン設定
                    if ($request->has('timezone') && Schema::hasColumn('users', 'timezone')) {
                        if (in_array($request->timezone, timezone_identifiers_list())) {
                            $updateData['timezone'] = $request->timezone;
                            $response['timezone'] = $request->timezone;
                        } else {
                            Log::warning('Invalid timezone provided', ['timezone' => $request->timezone]);
                        }
                    }
                    
                    if (!empty($updateData)) {
                        $user->update($updateData);
                        Log::info('User preferences updated', [
                            'user_id' => $user->id,
                            'updated_fields' => array_keys($updateData)
                        ]);
                    }
                } catch (\Exception $dbError) {
                    // データベース更新に失敗してもセッション更新は成功として扱う
                    Log::warning('Failed to update user preferences in database', [
                        'user_id' => Auth::id(),
                        'error' => $dbError->getMessage()
                    ]);
                    $response['warning'] = 'Settings saved to session but not persisted to database';
                }
            }

            return response()->json($response);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user preferences', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->only(['theme', 'language', 'timezone'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    });

    Route::get('/api/user/preferences', function () {
        try {
            $theme = session('theme', 'system');
            $language = session('locale', app()->getLocale());
            $timezone = config('app.timezone', 'UTC');
            
            // ユーザーがログインしている場合、データベースからも取得
            if (Auth::check()) {
                $user = Auth::user();
                
                if (Schema::hasColumn('users', 'theme_preference') && $user->theme_preference) {
                    $theme = $user->theme_preference;
                }
                
                if (Schema::hasColumn('users', 'timezone') && $user->timezone) {
                    $timezone = $user->timezone;
                }
            }

            return response()->json([
                'success' => true,
                'theme' => $theme,
                'language' => $language,
                'timezone' => $timezone,
                'session_theme' => session('theme'),
                'app_locale' => app()->getLocale(),
                'available_themes' => ['light', 'dark', 'system'],
                'available_languages' => [
                    'en' => 'English',
                    'ja' => '日本語',
                    'es' => 'Español',
                    'fr' => 'Français',
                    'de' => 'Deutsch'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching user preferences', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    });

    // テーマ情報取得API（公開エンドポイント）
    Route::get('/api/theme/info', function () {
        try {
            return response()->json([
                'success' => true,
                'current_theme' => session('theme', 'system'),
                'available_themes' => [
                    'light' => [
                        'name' => 'Light',
                        'description' => 'Light theme for daytime use'
                    ],
                    'dark' => [
                        'name' => 'Dark', 
                        'description' => 'Dark theme for nighttime use'
                    ],
                    'system' => [
                        'name' => 'System',
                        'description' => 'Follow system preference'
                    ]
                ],
                'system_preference' => request()->header('Sec-CH-Prefers-Color-Scheme', 'light')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get theme info'
            ], 500);
        }
    });
});

require __DIR__.'/auth.php';