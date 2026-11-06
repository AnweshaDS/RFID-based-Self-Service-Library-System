<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patron_verification_captures', function (Blueprint $table) {
            $table->string('review_status', 16)->nullable()->after('matched_at');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->json('transaction_items')->nullable()->after('review_note');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('patron_verification_captures', function (Blueprint $table) {
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'reviewed_by', 'reviewed_at', 'review_note', 'transaction_items']);
        });
    }
};