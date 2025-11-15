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
        Schema::create('timelogs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->dateTime('time')->index();
            $table->unsignedSmallInteger('device')->index();
            $table->string('uid')->index();
            $table->unsignedTinyInteger('mode');
            $table->unsignedTinyInteger('state');
            $table->boolean('shadow')->default(false);
            $table->boolean('pseudo')->default(false);
            $table->boolean('masked')->default(false);
            $table->boolean('recast')->index()->default(false);
            $table->ulid('timelog_id')->index()->nullable();

            $table->unique(['device', 'uid', 'time', 'state', 'mode']);
        });

        Schema::table('timelogs', function (Blueprint $table) {
            $table->foreign('device')->references('uid')->on('scanners')->cascadeOnUpdate()->cascadeOnDelete()->change();
            $table->foreign('timelog_id')->index()->nullable()->references('id')->on('timelogs')->cascadeOnUpdate()->cascadeOnDelete()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timelogs');
    }
};
