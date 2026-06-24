<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Notifications\CustomResetPassword;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasUuids, SoftDeletes;

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'pix_key',
        'fcm_token',
        'profile_image',
        'whatsapp_notifications',
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
        ];
    }

    /**
     * Get the full URL for the profile image.
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        if (!$this->profile_image) {
            return null;
        }

        return asset('storage/' . $this->profile_image);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function ownedTrips()
    {
        return $this->hasMany(Trip::class, 'created_by');
    }

    public function hasActivePremium(): bool
    {
        return in_array($this->subscription?->status, ['active', 'trialing']);
    }

    public function activeGroupsCount(): int
    {
        return $this->ownedTrips()
            ->where('recurring_expenses_enabled', true)
            ->count();
    }

    public function isWithinGroupQuota(): bool
    {
        $limit = config("billing.plans.{$this->subscription?->plan}.group_limit");
        return $limit === null || $this->activeGroupsCount() <= $limit;
    }

    public function canActivateNewGroup(): bool
    {
        if (!$this->hasActivePremium()) return false;
        $limit = config("billing.plans.{$this->subscription->plan}.group_limit");
        return $limit === null || $this->activeGroupsCount() < $limit;
    }
}
