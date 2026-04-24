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
