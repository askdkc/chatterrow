<?php

namespace App\Models;

use App\Support\MarkdownedDocDispatcher;
use App\Support\MarkdownedDocGenerator;
use App\Support\MarkdownSearchIndex;
use App\Support\StoredFilePreviewDispatcher;
use App\Support\StoredFilePreviewGenerator;
use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
 * @property string|null $markdown_path
 * @property string|null $markdown_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read User|null $uploader
 * @property-read Model|null $attachable
 * @property-read string $stream_url
 * @property-read string $download_url
 * @property-read string|null $thumbnail_url
 */
#[Appends(['stream_url', 'download_url', 'thumbnail_url'])]
#[Fillable(['server_id', 'uploaded_by', 'attachable_type', 'attachable_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'preview_path', 'preview_status', 'markdown_path', 'markdown_status'])]
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

            if (MarkdownedDocGenerator::supports($storedFile->original_name)) {
                $storedFile->markdown_path = null;
                $storedFile->markdown_status = 'pending';
            } else {
                $storedFile->markdown_path = null;
                $storedFile->markdown_status = null;
            }
        });

        static::created(function (StoredFile $storedFile): void {
            if ($storedFile->preview_status === 'pending') {
                app(StoredFilePreviewDispatcher::class)
                    ->dispatchAfterCommit($storedFile->id, $storedFile->path);
            }

            if ($storedFile->markdown_status === 'pending') {
                app(MarkdownedDocDispatcher::class)
                    ->dispatchAfterCommit($storedFile->id, $storedFile->path);
            }
        });

        static::deleting(function (StoredFile $storedFile): void {
            if ($storedFile->markdown_path !== null) {
                Storage::disk('markdowned')->delete($storedFile->markdown_path);
            }

            app(MarkdownSearchIndex::class)->remove($storedFile->id);
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

    /** @return Attribute<string, never> */
    protected function streamUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('servers.files.stream', [$this->server_id, $this]));
    }

    /** @return Attribute<string, never> */
    protected function downloadUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('servers.files.download', [$this->server_id, $this]));
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->preview_status !== 'ready' || $this->preview_path === null) {
            return null;
        }

        return route('servers.files.thumbnail', [$this->server_id, $this]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
