<?php

namespace App\Models;

use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property int $id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $description
 * @property string|null $icon_path
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, ServerInvitation> $invitations
 * @property-read Collection<int, Channel> $channels
 * @property-read string|null $icon_url
 */
#[Appends(['icon_url'])]
#[Fillable(['created_by', 'name', 'description', 'icon_path', 'starts_on', 'ends_on', 'archived_at'])]
#[Hidden(['icon_path'])]
class Server extends Model
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Server $server): void {
            if ($server->icon_path === null) {
                return;
            }

            $disk = Storage::disk('local');

            if ($disk->exists($server->icon_path)
                && ! $disk->delete($server->icon_path)) {
                throw new RuntimeException('Project icon deletion failed.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isAdministrator(User $user): bool
    {
        return $this->created_by === $user->id
            || $this->members()
                ->whereKey($user->id)
                ->wherePivot('role', self::ROLE_ADMIN)
                ->exists();
    }

    /** @return HasMany<ServerInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(ServerInvitation::class);
    }

    /** @return HasMany<Channel, $this> */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * All todos across the server's channels.
     *
     * @return HasManyThrough<Todo, Channel, $this>
     */
    public function todos(): HasManyThrough
    {
        return $this->hasManyThrough(Todo::class, Channel::class);
    }

    /** @return HasMany<StoredFile, $this> */
    public function storedFiles(): HasMany
    {
        return $this->hasMany(StoredFile::class);
    }

    /**
     * Limit servers to those the user belongs to.
     *
     * @param  Builder<Server>  $query
     * @return Builder<Server>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'members',
            fn (Builder $memberQuery) => $memberQuery->whereKey($user->id),
        );
    }

    /**
     * Limit servers to projects that have not been archived.
     *
     * @param  Builder<Server>  $query
     * @return Builder<Server>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'archived_at' => 'datetime',
        ];
    }

    /** @return Attribute<string|null, never> */
    protected function iconUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->icon_path !== null
            ? route('servers.icon', $this).'?v='.substr(sha1($this->icon_path), 0, 12)
            : null);
    }
}
