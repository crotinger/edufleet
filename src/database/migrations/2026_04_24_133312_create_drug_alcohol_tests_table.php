<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_alcohol_tests', function (Blueprint $table) {
            $table->id();
            // Driver whose test this is. Restrict-on-delete — confidential
            // records shouldn't vanish with a driver deletion.
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();

            // Reason for the test (49 CFR §382 Subpart C)
            //   pre_employment, random, reasonable_suspicion,
            //   post_accident, return_to_duty, follow_up
            $table->string('test_type', 24)->index();

            // What was tested for: drug (urine, 5-panel+), alcohol (breath),
            // or both (pre-employment is drug-only per FMCSA but some
            // employers bundle alcohol into random events).
            $table->string('test_category', 16)->index();

            // Scheduled — when selected / notified to test
            $table->date('scheduled_for')->nullable();
            // Completed — when specimen/breath actually collected
            $table->date('completed_on')->nullable();
            // Result reported back by the MRO / BAT
            $table->date('reported_on')->nullable();

            // Outcome: negative, positive, refusal, cancelled,
            // dilute_negative, dilute_positive, adulterated
            $table->string('result', 24)->nullable()->index();

            $table->string('collection_site', 191)->nullable();
            $table->boolean('mro_reviewed')->default(false);
            $table->text('substances_tested_for')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver_id', 'completed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_alcohol_tests');
    }
};
