<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Server> $servers
 * @property-read Collection<int, ServerInvitation> $receivedServerInvitations
 * @property-read Collection<int, ServerInvitation> $sentServerInvitations
 * @property-read Collection<int, MessageMention> $messageMentions
 * @property-read Collection<int, MessageReaction> $messageReactions
 * @property-read Collection<int, ProjectFolder> $projectFolders
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return BelongsToMany<Server, $this> */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class)
            ->withPivot('role', 'project_folder_id')
            ->withTimestamps();
    }

    /** @return HasMany<ServerInvitation, $this> */
    public function receivedServerInvitations(): HasMany
    {
        return $this->hasMany(ServerInvitation::class);
    }

    /** @return HasMany<ServerInvitation, $this> */
    public function sentServerInvitations(): HasMany
    {
        return $this->hasMany(ServerInvitation::class, 'invited_by');
    }

    /** @return HasMany<MessageMention, $this> */
    public function messageMentions(): HasMany
    {
        return $this->hasMany(MessageMention::class);
    }

    /** @return HasMany<MessageReaction, $this> */
    public function messageReactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /** @return HasMany<ProjectFolder, $this> */
    public function projectFolders(): HasMany
    {
        return $this->hasMany(ProjectFolder::class);
    }

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
