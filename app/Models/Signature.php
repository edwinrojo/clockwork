<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LSNepomuceno\LaravelA1PdfSign\Exceptions\ProcessRunTimeException;
use LSNepomuceno\LaravelA1PdfSign\Sign\ManageCert;
use SensitiveParameter;

class Signature extends Model
{
    use HasFactory, HasUlids; // SoftDeletes;

    protected $fillable = [
        'specimen',
        'certificate',
        'password',
        'signaturable_id',
        'signaturable_type',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function signaturable()
    {
        return $this->morphTo();
    }

    public function specimen(): Attribute
    {
        return Attribute::make(
            fn (mixed $specimen): ?string => $specimen !== null ? decrypt(stream_get_contents($specimen)) : null,
            fn (mixed $specimen): mixed => $specimen !== null ? encrypt($specimen) : null,
        )->shouldCache();
    }

    public function certificate(): Attribute
    {
        return Attribute::make(
            fn (mixed $certificate): ?string => $certificate !== null ? decrypt(stream_get_contents($certificate)) : null,
            fn (mixed $certificate): mixed => $certificate !== null ? encrypt($certificate) : null,
        )->shouldCache();
    }

    public function dimension(): Attribute
    {
        return Attribute::make(
            function () {
                [$x, $y] = getimagesizefromstring($this->specimen);

                return [$x, $y];
            }
        )->shouldCache();
    }

    public function landscape(): Attribute
    {
        return Attribute::make(
            function () {
                [$x, $y] = $this->dimension;

                return $x > $y;
            }
        )->shouldCache();
    }

    public function portrait(): Attribute
    {
        return Attribute::make(
            function () {
                [$x, $y] = $this->dimension;

                return $y > $x;
            }
        )->shouldCache();
    }

    public function specimenBase64(): Attribute
    {
        return Attribute::make(
            fn () => explode(',', $this->specimen)[1]
        )->shouldCache();
    }

    public function certificateBase64(): Attribute
    {
        return Attribute::make(
            fn () => explode(',', $this->certificate)[1]
        )->shouldCache();
    }

    public function verify(#[SensitiveParameter] string $password): bool
    {
        try {
            $tmp = tempnam(sys_get_temp_dir(), "{$this->id}-");

            file_put_contents($tmp, base64_decode($this->certificateBase64));

            (new ManageCert)->fromPfx($tmp, $password);

            return true;
        } catch (ProcessRunTimeException $exception) {
            if (str($exception->getMessage())->contains('password')) {
                return false;
            }

            throw $exception;
        }
    }
}
