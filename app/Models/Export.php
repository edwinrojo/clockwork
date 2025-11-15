<?php

namespace App\Models;

use finfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Export extends Model
{
    use HasFactory, HasUlids, Prunable;

    protected $fillable = [
        'filename',
        'digest',
        'details',
        'content',
        'disk',
        'downloads',
        'downloaded_at',
    ];

    protected $casts = [
        'details' => 'object',
    ];

    public static function booted(): void
    {
        static::saved(function (Export $export) {
            if ($export->wasChanged('content') || $export->wasChanged('filename') || $export->wasChanged('disk')) {
                $hash = match (true) {
                    $export->disk !== null => hash('sha512', Storage::disk($export->disk)->get($export->filename)),
                    $export->disk === null && file_exists($export->filename) => hash_file('sha512', $export->filename),
                    $export->content !== null => hash('sha512', $export->content),
                    default => null,
                };

                $export->updateQuietly(['digest' => $hash]);
            }
        });

        static::deleting(function (Export $export) {
            if ($export->disk !== null) {
                Storage::disk($export->disk)->delete($export->filename);
            } elseif (file_exists($export->filename)) {
                unlink($export->filename);
            }

            $export->signers()->lazyById()->each->delete();
        });
    }

    public function content(): Attribute
    {
        return Attribute::make(
            function (mixed $content): ?string {
                if ($this->disk !== null && in_array($this->disk, ['public', 'local', 'azure'])) {
                    return Storage::disk($this->disk)->get($this->filename);
                }

                if (file_exists($this->filename)) {
                    return file_get_contents($this->filename);
                }

                if (is_resource($content)) {
                    $rawContent = stream_get_contents($content);
                    $decoded = base64_decode($rawContent, true);

                    return $decoded !== false ? $decoded : $rawContent;
                }

                if (is_string($content)) {
                    $decoded = base64_decode($content, true);

                    return $decoded !== false ? $decoded : $content;
                }

                return null;
            },
            function (mixed $content, array $attributes) {
                if ($attributes['disk'] !== null && in_array($attributes['disk'], ['public', 'local', 'azure'])) {
                    return null;
                }

                if (file_exists($attributes['filename'])) {
                    return null;
                }

                return $content ? base64_encode($content) : null;
            },
        )->shouldCache();
    }

    public function mimetype(): Attribute
    {
        return Attribute::make(
            function (): ?string {
                if ($this->disk !== null && in_array($this->disk, ['public', 'local', 'azure'])) {
                    /** @var FilesystemAdapter $disk */
                    $disk = Storage::disk($this->disk);

                    return $disk->mimetype($this->filename);
                }

                if ($this->disk === null && file_exists($this->filename)) {
                    return mime_content_type($this->filename);
                }

                if ($this->content !== null) {
                    return (new finfo(FILEINFO_MIME_TYPE))->buffer($this->content);
                }

                return null;
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function prunable(): Builder
    {
        return static::query()
            ->orWhere(fn ($query) => $query->whereNotNull('exportable_id')->whereNotNull('exportable_type')->whereDoesntHave('exportable', fn ($q) => $q->withoutGlobalScopes()))
            ->orWhere(fn ($query) => $query->whereNotNull('user_id')->where('created_at', '<=', now()->subMinutes(15)))
            ->orWhere(fn ($query) => $query->whereNull('user_id')->whereNull('exportable_id')->whereNull('exportable_type'));
    }

    public function scopeTimesheet(Builder $query): void
    {
        $query->where('exportable_type', Timesheet::class);
    }

    public function signers(): MorphMany
    {
        return $this->morphMany(Signer::class, 'signable');
    }

    public function file(): ?string
    {
        if ($this->content !== null) {
            return $this->content;
        }

        if ($this->disk !== null) {
            return Storage::disk($this->disk)->get($this->filename);
        }

        return null;
    }
}
