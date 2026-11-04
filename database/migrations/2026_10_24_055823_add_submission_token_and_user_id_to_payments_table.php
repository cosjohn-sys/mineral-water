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
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['sale_id', 'submission_token']);
            // Preserve the enum check when SQLite rebuilds this table.
            $table->enum('payment_method', ['cash', 'gcash'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['sale_id', 'submission_token']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('submission_token');
            $table->enum('payment_method', ['cash', 'gcash'])->change();
        });
    }
};
