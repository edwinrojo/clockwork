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
        Schema::createExtensionIfNotExists('pg_trgm');
        
        Schema::create('employees', function (Blueprint $table) {
            $first_name = 'first_name';
            $middle_name = "CASE WHEN TRIM(BOTH '' FROM middle_name) = '' THEN '' ELSE ', ' END || TRIM(BOTH '' FROM middle_name)";
            $last_name = "last_name || ', '";
            $middle_initial = "CASE WHEN TRIM(BOTH '' FROM middle_name) = '' THEN '' ELSE ' ' END || SUBSTRING(TRIM(BOTH '' FROM middle_name), 1, 1) || CASE WHEN LENGTH(TRIM(BOTH '' FROM middle_name)) <= 1 THEN '' ELSE '.' END";
            $qualifier_name = "CASE WHEN qualifier_name = '' THEN '' ELSE ', ' END || qualifier_name";

            $nameExpression = "$last_name || $first_name || $middle_initial || $qualifier_name";
            $fullNameExpression = "$last_name || $first_name || $middle_name || $qualifier_name";

            $table->ulid('id')->primary();
            $table->string('prefix_name', 60)->nullable();
            $table->string('suffix_name', 60)->nullable();
            $table->string('first_name', 60);
            $table->string('middle_name', 60)->default('');
            $table->string('last_name', 60);
            $table->string('qualifier_name', 60)->default('');
            $table->string('name', 240)->storedAs("TRIM(BOTH ' ' FROM {$nameExpression})");
            $table->string('full_name', 240)->storedAs("TRIM(BOTH ' ' FROM {$fullNameExpression})");
            $table->string('email')->unique()->nullable();
            $table->string('number')->unique()->nullable();
            $table->string('password')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('sex')->nullable();
            $table->string('designation')->nullable();
            $table->string('status')->default('');
            $table->string('substatus')->default('');
            $table->foreignUlid('office_id')->nullable();
            $table->char('uid', 8)->unique()->nullable();
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->unique(['first_name', 'middle_name', 'last_name', 'qualifier_name'], 'employees_unique');
            $table->rawIndex("name gin_trgm_ops", 'employees_name_idx')->algorithm('gin');
            $table->rawIndex("full_name gin_trgm_ops", 'employees_full_name_idx')->algorithm('gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
