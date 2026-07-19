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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->bigInteger('order_number')->unique();
            $table->integer('table_number')->nullable();
            $table->enum('status', ['PENDING', 'PREPARING', 'READY', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['UNPAID', 'PAID', 'PARTIAL'])->default('UNPAID');
            $table->string('payment_method')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
