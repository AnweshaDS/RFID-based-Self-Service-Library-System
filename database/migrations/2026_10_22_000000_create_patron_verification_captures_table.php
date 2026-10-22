<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patron_verification_captures', function (Blueprint $table) {
            $table->id();
            $table->string('patron_id')->index();
            $table->string('cardnumber')->nullable();
            $table->string('patron_name')->nullable();
            $table->string('action'); // 'borrow' or 'return'
            $table->string('barcode')->nullable();
            $table->string('photo_path');
            $table->boolean('face_detected')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patron_verification_captures');
    }
};