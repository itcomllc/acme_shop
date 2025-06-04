<?php

namespace App\Models;

use App\Models\Traits\HasRoles;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\{HasOne, HasMany};

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'square_customer_id',
        'password',
        'primary_role_id',
        'last_role_change',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_role_change' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * Get the user's active subscription (safer version)
     */
    public function getActiveSubscriptionAttribute(): ?Subscription
    {
        // Check if subscriptions relationship exists first
        if (!method_exists($this, 'subscriptions') || !$this->relationLoaded('subscriptions')) {
            try {
                return $this->subscriptions()->where('status', 'active')->first();
            } catch (\Exception $e) {
                // If subscriptions table doesn't exist yet, return null
                return null;
            }
        }
        
        return $this->subscriptions->where('status', 'active')->first();
    }

    /**
     * Get the user's active subscription relationship
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    /**
     * Get all subscriptions for the user
     */
    public function subscriptions(): HasMany
    {
        try {
            return $this->hasMany(Subscription::class);
        } catch (\Exception $e) {
            // Handle case where Subscription model or table doesn't exist yet
            \Log::info('Subscriptions table not available yet', ['error' => $e->getMessage()]);
            // Return empty collection wrapped in a relation-like object
            return $this->hasMany(static::class)->whereRaw('1 = 0'); // Always empty
        }
    }

    /**
     * Boot method to handle events
     */
    protected static function boot()
    {
        parent::boot();

        // ユーザー作成時に基本ロールを自動割り当て
        static::created(function ($user) {
            try {
                // デフォルトのユーザーロールを割り当て
                $userRole = Role::where('name', Role::USER)->first();
                if ($userRole && !$user->hasRole($userRole)) {
                    $user->assignRole($userRole);
                    $user->setPrimaryRole($userRole);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to assign default role to new user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        });

        // ログイン時刻の更新
        static::retrieved(function ($user) {
            // この処理は必要に応じて実装
        });
    }
}