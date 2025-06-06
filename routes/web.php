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

    // テーマAPI - 改善版（タイムゾーン更新強化）
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
                    
                    Log::info('API: Before user preferences update', [
                        'user_id' => $user->id,
                        'current_timezone' => $user->timezone,
                        'new_timezone' => $request->timezone,
                        'fillable' => $user->getFillable()
                    ]);

                    $updateData = [];
                    
                    // テーマ設定（preferencesに保存）
                    $preferences = $user->preferences ?? [];
                    $preferences['theme'] = $request->theme;
                    
                    if ($request->has('language')) {
                        $preferences['language'] = $request->language;
                    }
                    
                    $updateData['preferences'] = $preferences;
                    
                    // タイムゾーン設定（確実に更新）
                    if ($request->has('timezone')) {
                        if (in_array($request->timezone, timezone_identifiers_list())) {
                            $updateData['timezone'] = $request->timezone;
                            $response['timezone'] = $request->timezone;
                            
                            Log::info('API: Adding timezone to update data', [
                                'timezone' => $request->timezone
                            ]);
                        } else {
                            Log::warning('API: Invalid timezone provided', ['timezone' => $request->timezone]);
                        }
                    }
                    
                    if (!empty($updateData)) {
                        // fillableプロパティに含まれているかチェック
                        $fillable = $user->getFillable();
                        $missingFields = [];
                        
                        foreach (array_keys($updateData) as $field) {
                            if (!in_array($field, $fillable)) {
                                $missingFields[] = $field;
                            }
                        }
                        
                        if (!empty($missingFields)) {
                            Log::warning('API: Fields not in fillable', [
                                'missing_fields' => $missingFields,
                                'fillable' => $fillable
                            ]);
                        }

                        // 直接属性を設定してからsave
                        foreach ($updateData as $field => $value) {
                            $user->$field = $value;
                        }
                        
                        $saved = $user->save();
                        
                        Log::info('API: User preferences update result', [
                            'user_id' => $user->id,
                            'saved' => $saved,
                            'updated_fields' => array_keys($updateData),
                            'timezone_after_save' => $user->fresh()->timezone
                        ]);

                        if (!$saved) {
                            Log::error('API: Failed to save user preferences');
                            $response['warning'] = 'Failed to save preferences to database';
                        }
                    }
                } catch (\Exception $dbError) {
                    // データベース更新に失敗してもセッション更新は成功として扱う
                    Log::error('API: Failed to update user preferences in database', [
                        'user_id' => Auth::id(),
                        'error' => $dbError->getMessage(),
                        'trace' => $dbError->getTraceAsString()
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
            Log::error('API: Error updating user preferences', [
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
                
                // preferencesからテーマと言語を取得
                if ($user->preferences) {
                    if (isset($user->preferences['theme'])) {
                        $theme = $user->preferences['theme'];
                    }
                    if (isset($user->preferences['language'])) {
                        $language = $user->preferences['language'];
                    }
                }
                
                // タイムゾーンを取得
                if ($user->timezone) {
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
            Log::error('API: Error fetching user preferences', [
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