<?php

namespace App\Models;

use App\Enums\AttachmentClassification;
use finfo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasUlids;

    protected $fillable = [
        'filename',
        'digest',
        'content',
        'classification',
        'context',
        'disk',
        'attachmentable_id',
        'attachmentable_type',
    ];

    protected $casts = [
        'context' => 'object',
        'classification' => AttachmentClassification::class,
    ];

    public static function booted(): void
    {
        static::saved(function (self $attachment) {
            if ($attachment->wasChanged('content') || $attachment->wasChanged('filename') || $attachment->wasChanged('disk')) {
                $hash = match (true) {
                    $attachment->disk !== null => hash('sha512', Storage::disk($attachment->disk)->get($attachment->filename)),
                    $attachment->disk === null && file_exists($attachment->filename) => hash_file('sha512', $attachment->filename),
                    $attachment->content !== null => hash('sha512', $attachment->content),
                    default => null,
                };

                $attachment->updateQuietly(['digest' => $hash]);
            }
        });

        static::deleting(function (self $attachment) {
            if ($attachment->disk !== null) {
                Storage::disk($attachment->disk)->delete($attachment->filename);
            } elseif (file_exists($attachment->filename)) {
                unlink($attachment->filename);
            }

            $attachment->signers()->lazyById()->each->delete();
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

                return $content ? base64_decode(stream_get_contents($content)) : null;
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

    public function attachmentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signers(): MorphMany
    {
        return $this->morphMany(Signer::class, 'signable');
    }
}
