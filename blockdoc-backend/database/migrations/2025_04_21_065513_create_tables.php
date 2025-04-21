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
        // Create users table (simplified for testing)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Create fiscal_entries table
        Schema::create('fiscal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('fiscal_year');
            $table->string('fiscal_period');
            $table->string('document_type');
            $table->string('creator');
            $table->string('last_modifier')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // Create documents table
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('fiscal_entry_id')->nullable()->constrained()->onDelete('set null');
            $table->string('filename');
            $table->string('original_filename')->nullable();
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('hash', 128);
            $table->enum('blockchain_status', ['pending', 'confirmed', 'failed'])->default('pending');
            $table->string('transaction_hash')->nullable();
            $table->string('blockchain_network')->default('Sepolia Testnet');
            $table->timestamp('blockchain_timestamp')->nullable();
            $table->timestamps();
        });

        // Create sessions table (for web sessions)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('fiscal_entries');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};