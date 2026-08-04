<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $color
 * @property string|null $icon_path
 * @property int $position
 * @property-read string|null $icon_url
 * @property-read User $user
 * @property-read Collection<int, Server> $servers
 */
#[Appends(['icon_url'])]
#[Fillable(['user_id', 'name', 'color', 'icon_path', 'position'])]
#[Hidden(['icon_path'])]
class ProjectFolder extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (ProjectFolder $folder): void {
            if ($folder->icon_path !== null
                && ! Storage::disk('local')->delete($folder->icon_path)) {
                throw new RuntimeException('Project folder icon deletion failed.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Server, $this> */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(
            Server::class,
            'server_user',
            'project_folder_id',
            'server_id',
        );
    }

    /** @return Attribute<covariant string|null, never> */
    protected function iconUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->icon_path !== null
            ? route('project-folders.icon', $this).'?v='.substr(sha1($this->icon_path), 0, 12)
            : null);
    }
}
