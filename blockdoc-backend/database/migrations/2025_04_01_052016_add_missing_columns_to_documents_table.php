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
        Schema::table('documents', function (Blueprint $table) {
            // 既存のテーブルに新しいカラムを追加
            $table->string('original_filename')->nullable()->after('filename');
            $table->string('mime_type')->nullable()->after('original_filename');
            $table->bigInteger('size')->nullable()->after('mime_type');
            
            // また、blockchain_statusを拡張して「failed」状態も含めるようにする
            DB::statement("ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_blockchain_status_check");
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_blockchain_status_check CHECK (blockchain_status::text = ANY (ARRAY['pending'::character varying, 'confirmed'::character varying, 'failed'::character varying]::text[]))");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['original_filename', 'mime_type', 'size']);
            
            // blockchain_statusをリセット
            DB::statement("ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_blockchain_status_check");
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_blockchain_status_check CHECK (blockchain_status::text = ANY (ARRAY['pending'::character varying, 'confirmed'::character varying]::text[]))");
        });
    }
};