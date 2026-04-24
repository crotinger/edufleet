<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\DrugAlcoholTest;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class DrugAlcoholCompliance extends Page
{
    protected string $view = 'filament.pages.drug-alcohol-compliance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Drug/alcohol compliance';

    protected static ?string $title = 'Drug & alcohol testing — compliance';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 36;

    // FMCSA random-testing minimums (49 CFR §382.305).
    // As of 2024-2025 the drug rate is 50% and the alcohol rate is 10% of
    // average driver positions; adjust via these inputs when the Federal
    // Register publishes a change.
    public string $drugRate = '0.50';

    public string $alcoholRate = '0.10';

    public int $year;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_drug_alcohol_test') ?? false;
    }

    public function mount(): void
    {
        $this->year = (int) now()->year;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function yearRange(): array
    {
        return [
            CarbonImmutable::create($this->year, 1, 1)->startOfDay(),
            CarbonImmutable::create($this->year, 12, 31)->endOfDay(),
        ];
    }

    /** CDL pool — drivers subject to random testing.
     *
     * §382 applies to CDL holders. Class A and B are always CDLs, but "Class C"
     * is ambiguous — most states (including Kansas) issue plain Class C as the
     * standard non-commercial license. A Class C driver is only a CDL holder
     * when they carry at least one CDL endorsement (P, S, N, T, H, or X), so
     * staff with a regular Class C license are correctly excluded.
     */
    public function getPool(): Collection
    {
        return Driver::query()
            ->where('status', Driver::STATUS_ACTIVE)
            ->whereNotNull('license_number')
            ->where(function ($q) {
                $q->whereIn('license_class', ['A', 'B'])
                    ->orWhere(function ($q) {
                        $q->where('license_class', 'C')
                            ->whereNotNull('endorsements')
                            ->whereRaw("jsonb_array_length(endorsements::jsonb) > 0");
                    });
            })
            ->orderBy('last_name')
            ->get();
    }

    public function getSummary(): array
    {
        [$start, $end] = $this->yearRange();
        $pool = $this->getPool();
        $poolCount = $pool->count();
        $drugRate = (float) ($this->drugRate ?: 0);
        $alcoholRate = (float) ($this->alcoholRate ?: 0);

        $required = [
            'drug' => (int) ceil($poolCount * $drugRate),
            'alcohol' => (int) ceil($poolCount * $alcoholRate),
        ];

        $randomDrug = DrugAlcoholTest::query()
            ->where('test_type', DrugAlcoholTest::TYPE_RANDOM)
            ->whereIn('test_category', [DrugAlcoholTest::CATEGORY_DRUG, DrugAlcoholTest::CATEGORY_BOTH])
            ->whereBetween(\DB::raw('COALESCE(completed_on, scheduled_for)'), [$start->toDateString(), $end->toDateString()])
            ->whereNull('deleted_at')
            ->count();

        $randomAlcohol = DrugAlcoholTest::query()
            ->where('test_type', DrugAlcoholTest::TYPE_RANDOM)
            ->whereIn('test_category', [DrugAlcoholTest::CATEGORY_ALCOHOL, DrugAlcoholTest::CATEGORY_BOTH])
            ->whereBetween(\DB::raw('COALESCE(completed_on, scheduled_for)'), [$start->toDateString(), $end->toDateString()])
            ->whereNull('deleted_at')
            ->count();

        $violations = DrugAlcoholTest::query()
            ->whereIn('result', DrugAlcoholTest::violatingResults())
            ->whereBetween(\DB::raw('COALESCE(completed_on, scheduled_for)'), [$start->toDateString(), $end->toDateString()])
            ->whereNull('deleted_at')
            ->count();

        $open = DrugAlcoholTest::query()
            ->whereNotNull('scheduled_for')
            ->whereNull('completed_on')
            ->whereNull('deleted_at')
            ->count();

        return [
            'pool_count' => $poolCount,
            'required_drug' => $required['drug'],
            'required_alcohol' => $required['alcohol'],
            'actual_drug' => $randomDrug,
            'actual_alcohol' => $randomAlcohol,
            'drug_rate_pct' => $poolCount > 0 ? round(($randomDrug / $poolCount) * 100, 1) : null,
            'alcohol_rate_pct' => $poolCount > 0 ? round(($randomAlcohol / $poolCount) * 100, 1) : null,
            'violations' => $violations,
            'open_selections' => $open,
        ];
    }

    /** @return Collection<int, object> one row per driver with YTD test counts */
    public function getDriverRows(): Collection
    {
        [$start, $end] = $this->yearRange();
        $pool = $this->getPool();
        $poolIds = $pool->pluck('id')->all();

        $tests = DrugAlcoholTest::query()
            ->whereIn('driver_id', $poolIds)
            ->whereBetween(\DB::raw('COALESCE(completed_on, scheduled_for)'), [$start->toDateString(), $end->toDateString()])
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('driver_id');

        return $pool->map(function (Driver $d) use ($tests) {
            $driverTests = $tests[$d->id] ?? collect();
            return (object) [
                'driver' => $d,
                'total' => $driverTests->count(),
                'random_drug' => $driverTests->where('test_type', DrugAlcoholTest::TYPE_RANDOM)
                    ->whereIn('test_category', [DrugAlcoholTest::CATEGORY_DRUG, DrugAlcoholTest::CATEGORY_BOTH])
                    ->count(),
                'random_alcohol' => $driverTests->where('test_type', DrugAlcoholTest::TYPE_RANDOM)
                    ->whereIn('test_category', [DrugAlcoholTest::CATEGORY_ALCOHOL, DrugAlcoholTest::CATEGORY_BOTH])
                    ->count(),
                'last_random' => $driverTests->where('test_type', DrugAlcoholTest::TYPE_RANDOM)
                    ->map(fn (DrugAlcoholTest $t) => $t->completed_on ?? $t->scheduled_for)
                    ->filter()
                    ->sortDesc()
                    ->first(),
                'has_violation' => $driverTests->contains(fn (DrugAlcoholTest $t) => $t->isViolation()),
                'has_open' => $driverTests->contains(fn (DrugAlcoholTest $t) => $t->scheduled_for !== null && $t->completed_on === null),
            ];
        });
    }
}
