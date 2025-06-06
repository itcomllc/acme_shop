<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Settings\{Profile, Password, Appearance};

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Settings Routes
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // 新しいLivewireコンポーネントを使用
    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');

    // テーマAPI - 修正版
    Route::post('/api/user/preferences', function (Illuminate\Http\Request $request) {
        $request->validate([
            'theme' => 'required|in:light,dark,system',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        try {
            // セッションに保存
            session(['theme' => $request->theme]);
            
            if ($request->has('language')) {
                session(['locale' => $request->language]);
                app()->setLocale($request->language);
            }

            // ユーザーがログインしている場合、データベースにも保存
            if (Auth::check()) {
                $user = Auth::user();
                $updateData = [];
                
                // テーマ設定（カラムが存在する場合）
                if (\Schema::hasColumn('users', 'theme_preference')) {
                    $updateData['theme_preference'] = $request->theme;
                }
                
                // タイムゾーン設定
                if ($request->has('timezone') && \Schema::hasColumn('users', 'timezone')) {
                    if (in_array($request->timezone, timezone_identifiers_list())) {
                        $updateData['timezone'] = $request->timezone;
                    }
                }
                
                if (!empty($updateData)) {
                    $user->update($updateData);
                }
            }

            return response()->json([
                'success' => true,
                'theme' => $request->theme,
                'language' => $request->get('language', app()->getLocale()),
                'timezone' => $request->get('timezone'),
                'message' => 'Preferences updated successfully'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error updating user preferences', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => 'Internal server error'
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
                
                if (\Schema::hasColumn('users', 'theme_preference') && $user->theme_preference) {
                    $theme = $user->theme_preference;
                }
                
                if (\Schema::hasColumn('users', 'timezone') && $user->timezone) {
                    $timezone = $user->timezone;
                }
            }

            return response()->json([
                'success' => true,
                'theme' => $theme,
                'language' => $language,
                'timezone' => $timezone,
                'session_theme' => session('theme'),
                'app_locale' => app()->getLocale()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching user preferences', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch preferences'
            ], 500);
        }
    });
});

require __DIR__.'/auth.php';