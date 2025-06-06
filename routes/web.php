<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Settings Routes
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Route::post('/api/user/preferences', function (Illuminate\Http\Request $request) {
        $request->validate([
            'theme' => 'required|in:light,dark,system',
        ]);

        // セッションに保存
        session(['theme' => $request->theme]);

        // ユーザーがログインしている場合、データベースにも保存（オプション）
        if (Auth::check()) {
            $user = Auth::user();
            if (Schema::hasColumn('users', 'theme_preference')) {
                $user->update(['theme_preference' => $request->theme]);
            }
        }

        return response()->json([
            'success' => true,
            'theme' => $request->theme,
            'message' => 'Theme preference updated'
        ]);
    });

    Route::get('/api/user/preferences', function () {
        $theme = session('theme', 'system');
        
        // ユーザーがログインしている場合、データベースからも取得
        if (Auth::check()) {
            $user = Auth::user();
            if (Schema::hasColumn('users', 'theme_preference') && $user->theme_preference) {
                $theme = $user->theme_preference;
            }
        }

        return response()->json([
            'success' => true,
            'theme' => $theme,
            'timezone' => Auth::user()->timezone ?? config('app.timezone'),
            'language' => session('locale', 'en')
        ]);
    });
});

require __DIR__.'/auth.php';