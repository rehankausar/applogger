<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base migration for application_logs table.
 *
 * This migration contains universal columns only.
 * After publishing (php artisan vendor:publish --tag=applogger-migrations),
 * add your application-specific tenant columns before running php artisan migrate.
 *
 * Example tenant columns to add:
 *   $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
 *   $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->index();       // error, warning, info, debug
            $table->string('type', 50)->index();        // exception, payment, booking, etc.
            $table->text('message');
            $table->longText('context')->nullable();    // JSON encoded context data
            $table->longText('stack_trace')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();   // GET, POST, etc.
            $table->ipAddress('ip_address')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // ---------------------------------------------------------------
            // ADD YOUR APPLICATION-SPECIFIC TENANT COLUMNS HERE
            // Example:
            //   $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            //   $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            //   $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            // ---------------------------------------------------------------

            $table->timestamp('created_at')->index();
            $table->index(['level', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_logs');
    }
};
