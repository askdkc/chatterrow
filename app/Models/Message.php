<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property int $channel_id
 * @property int|null $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property bool $is_reminder
 * @property string|null $reminder_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read Channel $channel
 * @property-read User|null $user
 * @property-read Message|null $parent
 * @property-read Collection<int, Message> $replies
 * @property-read Collection<int, StoredFile> $attachments
 */
#[Fillable(['server_id', 'channel_id', 'user_id', 'parent_id', 'body', 'is_reminder', 'reminder_key'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Message, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return MorphMany<StoredFile, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(StoredFile::class, 'attachable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_reminder' => 'boolean',
        ];
    }
}
