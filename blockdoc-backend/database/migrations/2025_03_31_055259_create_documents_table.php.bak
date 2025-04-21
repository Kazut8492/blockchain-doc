<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('path');
            $table->string('hash', 128);
            $table->enum('blockchain_status', ['pending', 'confirmed'])->default('pending');
            $table->string('transaction_hash')->nullable();
            $table->string('blockchain_network')->default('Sepolia Testnet');
            $table->timestamp('blockchain_timestamp')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('documents');
    }
};