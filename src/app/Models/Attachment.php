<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Attachment extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('attachment');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public static function categories(): array
    {
        return [
            'license' => 'Driver\'s license',
            'medical' => 'DOT medical / CPR / first aid',
            'certificate' => 'Inspection certificate',
            'registration' => 'Registration document',
            'insurance' => 'Insurance card',
            'invoice' => 'Invoice / receipt',
            'photo' => 'Photo',
            'other' => 'Other',
        ];
    }

    /**
     * FMCSA Driver Qualification File components (49 CFR §391).
     * Required components every CDL driver must have on file.
     *
     * @return array<string, array{label: string, cite: string, required: bool}>
     */
    public static function dqfComponents(): array
    {
        return [
            'application' => [
                'label' => 'Driver\'s application for employment',
                'cite' => '49 CFR §391.21',
                'required' => true,
            ],
            'previous_employers' => [
                'label' => 'Previous-employer safety inquiry',
                'cite' => '49 CFR §391.23(a)(2)',
                'required' => true,
            ],
            'mvr' => [
                'label' => 'Motor Vehicle Record (MVR)',
                'cite' => '49 CFR §391.23(a)(1)',
                'required' => true,
            ],
            'annual_review' => [
                'label' => 'Annual review of driving record',
                'cite' => '49 CFR §391.25',
                'required' => true,
            ],
            'violations_cert' => [
                'label' => 'Annual certification of violations',
                'cite' => '49 CFR §391.27',
                'required' => true,
            ],
            'road_test' => [
                'label' => 'Road test certificate (or CDL in lieu)',
                'cite' => '49 CFR §391.31 / §391.33',
                'required' => true,
            ],
            'medical' => [
                'label' => 'DOT medical examiner\'s certificate',
                'cite' => '49 CFR §391.43',
                'required' => true,
            ],
            'drug_alcohol' => [
                'label' => 'Pre-employment drug & alcohol test',
                'cite' => '49 CFR §382.301 (retained separately per §382.401)',
                'required' => false,
            ],
            'training' => [
                'label' => 'ELDT / entry-level driver training',
                'cite' => '49 CFR §380 Subpart F',
                'required' => false,
            ],
        ];
    }

    public static function dqfComponentLabels(): array
    {
        $out = [];
        foreach (self::dqfComponents() as $key => $meta) {
            $out[$key] = $meta['label'];
        }
        return $out;
    }

    protected function humanSize(): Attribute
    {
        return Attribute::get(function () {
            $bytes = (int) $this->size_bytes;
            if ($bytes < 1024) return $bytes . ' B';
            $kb = $bytes / 1024;
            if ($kb < 1024) return round($kb, 1) . ' KB';
            $mb = $kb / 1024;
            if ($mb < 1024) return round($mb, 2) . ' MB';
            return round($mb / 1024, 2) . ' GB';
        });
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function downloadUrl(): string
    {
        return route('attachments.download', $this);
    }
}
