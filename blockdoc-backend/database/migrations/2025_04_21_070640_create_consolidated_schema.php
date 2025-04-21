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
        // Only create tables that don't exist yet
        
        // Create fiscal_entries table if it doesn't exist
        if (!Schema::hasTable('fiscal_entries')) {
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
        }

        // Add fiscal_entry_id to documents table if the column doesn't exist
        if (Schema::hasTable('documents') && !Schema::hasColumn('documents', 'fiscal_entry_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreignId('fiscal_entry_id')->nullable()->constrained()->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop tables/columns that we created
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'fiscal_entry_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropForeign(['fiscal_entry_id']);
                $table->dropColumn('fiscal_entry_id');
            });
        }

        if (Schema::hasTable('fiscal_entries')) {
            Schema::dropIfExists('fiscal_entries');
        }
    }
};