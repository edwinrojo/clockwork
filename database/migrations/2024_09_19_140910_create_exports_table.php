<?php

use App\Models\User;
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
        Schema::create('exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('filename');
            $table->char('digest', 128)->nullable();
            $table->jsonb('details')->nullable();
            $table->binary('content')->nullable();
            $table->string('disk')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamp('downloaded_at')->nullable();
            $table->nullableUlidMorphs('exportable');
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['exportable_id', 'exportable_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
