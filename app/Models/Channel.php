<?php

namespace App\Models;

use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read User|null $creator
 * @property-read Collection<int, Message> $messages
 * @property-read Collection<int, Todo> $todos
 */
#[Fillable(['server_id', 'created_by', 'name', 'description', 'starts_on', 'ends_on'])]
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory;

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<Todo, $this> */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
