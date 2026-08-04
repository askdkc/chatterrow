<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property int|null $invited_by
 * @property int|null $user_id
 * @property string $email
 * @property string $token_hash
 * @property string $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read User|null $inviter
 * @property-read User|null $user
 */
#[Fillable([
    'server_id',
    'invited_by',
    'user_id',
    'email',
    'token_hash',
    'status',
    'sent_at',
    'responded_at',
])]
#[Hidden(['token_hash'])]
class ServerInvitation extends Model
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_PENDING = 'pending';

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit invitations to those addressed to the supplied user.
     *
     * @param  Builder<ServerInvitation>  $query
     * @return Builder<ServerInvitation>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $recipientQuery) use ($user): void {
            $recipientQuery
                ->where('user_id', $user->id)
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]);
        });
    }

    public function isFor(User $user): bool
    {
        return $this->user_id === $user->id
            || mb_strtolower($this->email) === mb_strtolower($user->email);
    }

    public static function findByPlainToken(string $token): ?self
    {
        if (strlen($token) !== 64) {
            return null;
        }

        return self::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }
}
