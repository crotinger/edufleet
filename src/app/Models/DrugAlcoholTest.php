<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DrugAlcoholTest extends Model
{
    use HasAttachments;
    use LogsActivity;
    use SoftDeletes;

    public const TYPE_PRE_EMPLOYMENT = 'pre_employment';
    public const TYPE_RANDOM = 'random';
    public const TYPE_REASONABLE_SUSPICION = 'reasonable_suspicion';
    public const TYPE_POST_ACCIDENT = 'post_accident';
    public const TYPE_RETURN_TO_DUTY = 'return_to_duty';
    public const TYPE_FOLLOW_UP = 'follow_up';

    public const CATEGORY_DRUG = 'drug';
    public const CATEGORY_ALCOHOL = 'alcohol';
    public const CATEGORY_BOTH = 'both';

    public const RESULT_NEGATIVE = 'negative';
    public const RESULT_POSITIVE = 'positive';
    public const RESULT_REFUSAL = 'refusal';
    public const RESULT_CANCELLED = 'cancelled';
    public const RESULT_DILUTE_NEGATIVE = 'dilute_negative';
    public const RESULT_DILUTE_POSITIVE = 'dilute_positive';
    public const RESULT_ADULTERATED = 'adulterated';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'completed_on' => 'date',
            'reported_on' => 'date',
            'mro_reviewed' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('drug_alcohol_test');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public static function testTypes(): array
    {
        return [
            self::TYPE_PRE_EMPLOYMENT => 'Pre-employment',
            self::TYPE_RANDOM => 'Random',
            self::TYPE_REASONABLE_SUSPICION => 'Reasonable suspicion',
            self::TYPE_POST_ACCIDENT => 'Post-accident',
            self::TYPE_RETURN_TO_DUTY => 'Return to duty',
            self::TYPE_FOLLOW_UP => 'Follow-up',
        ];
    }

    public static function categories(): array
    {
        return [
            self::CATEGORY_DRUG => 'Drug',
            self::CATEGORY_ALCOHOL => 'Alcohol',
            self::CATEGORY_BOTH => 'Both',
        ];
    }

    public static function results(): array
    {
        return [
            self::RESULT_NEGATIVE => 'Negative',
            self::RESULT_POSITIVE => 'Positive',
            self::RESULT_REFUSAL => 'Refusal',
            self::RESULT_CANCELLED => 'Cancelled',
            self::RESULT_DILUTE_NEGATIVE => 'Dilute — negative',
            self::RESULT_DILUTE_POSITIVE => 'Dilute — positive',
            self::RESULT_ADULTERATED => 'Adulterated',
        ];
    }

    /** Results that count as a rule violation (for compliance + SAP referral). */
    public static function violatingResults(): array
    {
        return [
            self::RESULT_POSITIVE,
            self::RESULT_REFUSAL,
            self::RESULT_DILUTE_POSITIVE,
            self::RESULT_ADULTERATED,
        ];
    }

    public function isViolation(): bool
    {
        return in_array($this->result, self::violatingResults(), true);
    }
}
