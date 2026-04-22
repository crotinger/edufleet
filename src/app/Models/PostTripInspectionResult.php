<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTripInspectionResult extends Model
{
    public const PASS = 'pass';
    public const FAIL = 'fail';
    public const NA = 'na';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'was_critical' => 'boolean',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(PostTripInspection::class, 'post_trip_inspection_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplateItem::class, 'inspection_template_item_id');
    }

    public static function resultLabels(): array
    {
        return [
            self::PASS => 'Pass',
            self::FAIL => 'Fail',
            self::NA => 'N/A',
        ];
    }
}
