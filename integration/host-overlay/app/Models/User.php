<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'active_company_id',
        'name',
        'email',
        'password',
        'avatar',
        'phone',
        'notifications_muted',
        'last_seen_at',
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
            'notifications_muted' => 'boolean',
            'last_seen_at' => 'datetime',
            'active_company_id' => 'integer',
        ];
    }

    /**
     * Check if user is online (active within last 5 minutes).
     */
    public function isOnline(): bool
    {
        if (! $this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gt(now()->subMinutes(5));
    }

    public function companyMemberships()
    {
        return $this->hasMany(\App\Domains\WorkCore\System\Models\CompanyMember::class, 'user_id');
    }

    public function activeCompany()
    {
        return $this->belongsTo(\App\Domains\WorkCore\System\Models\Company::class, 'active_company_id');
    }
}
