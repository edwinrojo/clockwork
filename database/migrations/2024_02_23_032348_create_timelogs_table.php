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
            $table->boolean('shadow')->default(false)->index();
            $table->boolean('pseudo')->default(false)->index();
            $table->boolean('masked')->default(false)->index();
            $table->boolean('recast')->default(false)->index();
            $table->boolean('cloned')->default(false)->index();
            $table->boolean('orphan')->storedAs('recast AND timelog_id IS NULL')->index();
            $table->ulid('timelog_id')->nullable();

            $table->unique(['device', 'uid', 'time', 'state', 'mode']);
            $table->index('timelog_id')->where('recast', true);
        });

        Schema::createFunctionOrReplace(
            name: 'timelogs_compute_cloned',
            parameters: [],
            return: 'trigger',
            language: 'plpgsql',
            body: <<<'SQL'
                BEGIN
                    NEW.cloned :=
                        EXISTS (
                            SELECT 1
                            FROM timelogs sub
                            WHERE sub.timelog_id = NEW.id
                            AND sub.recast = TRUE
                        );
                    RETURN NEW;
                END;
                SQL
        );

        Schema::table('timelogs', function (Blueprint $table) {
            $table->foreign('device')
                ->index()
                ->references('uid')
                ->on('scanners')
                ->cascadeOnUpdate()
                ->cascadeOnDelete()
                ->change();

            $table->foreign('timelog_id')
                ->nullable()
                ->references('id')
                ->on('timelogs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete()
                ->change();

            $table->trigger(
                'trigger_timelogs_compute_cloned',
                'timelogs_compute_cloned()',
                'BEFORE INSERT OR UPDATE'
            )->forEachRow();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timelogs', function (Blueprint $table) {
            $table->dropTrigger('trigger_timelogs_compute_cloned');
        });

        Schema::dropFunction('timelogs_compute_cloned');

        Schema::dropIfExists('timelogs');
    }
};
