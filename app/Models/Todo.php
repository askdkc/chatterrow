<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $channel_id
 * @property int|null $assignee_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $details
 * @property Carbon|null $starts_at
 * @property Carbon|null $due_at
 * @property string $due_timezone
 * @property string $priority
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property int $position
 * @property Carbon|null $reminded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Channel $channel
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read Collection<int, StoredFile> $attachments
 */
#[Fillable(['channel_id', 'assignee_id', 'created_by', 'title', 'details', 'starts_at', 'due_at', 'due_timezone', 'priority', 'completed_at', 'completed_by', 'position', 'reminded_at'])]
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }
}
