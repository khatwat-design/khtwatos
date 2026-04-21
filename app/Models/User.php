<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'calendly_url',
    'is_bookable',
    'availability_days',
    'availability_start_time',
    'availability_end_time',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_bookable' => 'boolean',
            'availability_days' => 'array',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['allocation_percent', 'is_lead'])
            ->withTimestamps();
    }

    public function hostedMeetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'user_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function managedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'account_manager_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHrManager(): bool
    {
        return $this->teams()
            ->where('slug', 'hr')
            ->wherePivot('is_lead', true)
            ->exists();
    }
}
