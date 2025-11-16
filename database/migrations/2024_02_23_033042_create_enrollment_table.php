<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollment', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('uid');
            $table->foreignUlid('employee_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignUlid('scanner_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedSmallInteger('device')->nullable()->index();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['employee_id', 'scanner_id']);
            $table->unique(['scanner_id', 'uid']);
            $table->index(['uid', 'device'], 'enrollment_uid_device_active_idx')
                ->where('active', true);
            $table->index(['employee_id', 'uid', 'device'])
                ->where('active', true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment');
    }
};
