<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patron_verification_captures', function (Blueprint $table) {
            $table->string('match_status', 24)->nullable()->after('face_detected');
            $table->decimal('match_score', 5, 3)->nullable()->after('match_status');
            $table->timestamp('matched_at')->nullable()->after('match_score');
            $table->index('match_status');
        });
    }

    public function down(): void
    {
        Schema::table('patron_verification_captures', function (Blueprint $table) {
            $table->dropIndex(['match_status']);
            $table->dropColumn(['match_status', 'match_score', 'matched_at']);
        });
    }
};