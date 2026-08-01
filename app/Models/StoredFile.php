<?php

namespace App\Models;

use App\Support\StoredFilePreviewDispatcher;
use App\Support\StoredFilePreviewGenerator;
use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property int|null $uploaded_by
 * @property string|null $attachable_type
 * @property int|null $attachable_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $preview_path
 * @property string|null $preview_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read User|null $uploader
 * @property-read Model|null $attachable
 */
#[Fillable(['server_id', 'uploaded_by', 'attachable_type', 'attachable_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'preview_path', 'preview_status'])]
class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = ['disk' => 'local'];

    protected static function booted(): void
    {
        static::creating(function (StoredFile $storedFile): void {
            if (StoredFilePreviewGenerator::supports($storedFile->original_name)) {
                $storedFile->preview_path = null;
                $storedFile->preview_status = 'pending';
            } else {
                $storedFile->preview_path = null;
                $storedFile->preview_status = null;
            }
        });

        static::created(function (StoredFile $storedFile): void {
            if ($storedFile->preview_status === 'pending') {
                app(StoredFilePreviewDispatcher::class)
                    ->dispatchAfterCommit($storedFile->id, $storedFile->path);
            }
        });
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
