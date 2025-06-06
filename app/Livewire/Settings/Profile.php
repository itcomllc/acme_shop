<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Illuminate\Support\Facades\{Auth, Hash, Log};
use Illuminate\Validation\ValidationException;

class Profile extends Component
{
    public string $name = '';
    public string $email = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    
    public function mount(): void
    {
        $this->loadUserData();
    }

    public function hydrate(): void
    {
        // hydrate時にデータが空の場合は再読み込み
        if (empty($this->name) || empty($this->email)) {
            $this->loadUserData();
        }
    }

    private function loadUserData(): void
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('Profile component: No authenticated user');
                return;
            }

            // 確実にプロパティを設定
            $this->name = $user->name ?? '';
            $this->email = $user->email ?? '';
            
            // パスワードフィールドは常に空
            $this->current_password = '';
            $this->password = '';
            $this->password_confirmation = '';

            Log::info('Profile data loaded', [
                'user_id' => $user->id,
                'name_loaded' => !empty($this->name),
                'email_loaded' => !empty($this->email)
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading profile data', [
                'error' => $e->getMessage()
            ]);
            
            // フォールバック値
            $this->name = '';
            $this->email = '';
            $this->current_password = '';
            $this->password = '';
            $this->password_confirmation = '';
        }
    }
    
    public function updateProfile(): void
    {
        try {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . Auth::id()],
            ]);

            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $updated = $user->update([
                'name' => trim($this->name),
                'email' => trim($this->email),
            ]);

            if (!$updated) {
                throw new \Exception('Failed to update user profile');
            }

            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'name' => $this->name,
                'email' => $this->email
            ]);

            $this->dispatch('profile-updated');
            session()->flash('status', 'profile-updated');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error updating profile', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            $this->addError('general', 'Failed to update profile. Please try again.');
        }
    }
    
    public function updatePassword(): void
    {
        try {
            $this->validate([
                'current_password' => ['required', 'current_password'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $updated = $user->update([
                'password' => Hash::make($this->password),
            ]);

            if (!$updated) {
                throw new \Exception('Failed to update password');
            }

            // パスワードフィールドをリセット
            $this->current_password = '';
            $this->password = '';
            $this->password_confirmation = '';

            Log::info('Password updated successfully', ['user_id' => $user->id]);

            $this->dispatch('password-updated');
            session()->flash('status', 'password-updated');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error updating password', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            $this->addError('current_password', 'Failed to update password. Please try again.');
        }
    }
    
    public function resendVerificationNotification(): void
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return;
            }

            if ($user->hasVerifiedEmail()) {
                $this->redirectIntended(default: route('dashboard', absolute: false));
                return;
            }

            $user->sendEmailVerificationNotification();
            session()->flash('status', 'verification-link-sent');

            Log::info('Verification email resent', ['user_id' => $user->id]);

        } catch (\Exception $e) {
            Log::error('Error resending verification email', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.settings.profile');
    }
}