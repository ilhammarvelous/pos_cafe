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
        Schema::create('pager_numbers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('number')->unique();
            $table->enum('status', ['AVAILABLE', 'IN_USE'])->default('AVAILABLE');
            $table->uuid('current_order_id')->nullable();
            $table->enum('buzzer_status', ['PENDING', 'RINGING', 'ACKNOWLEDGED'])->default('PENDING');
            $table->timestamp('created_at')->useCurrent();

            // Foreign key
            $table->foreign('current_order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pager_numbers');
    }
};
