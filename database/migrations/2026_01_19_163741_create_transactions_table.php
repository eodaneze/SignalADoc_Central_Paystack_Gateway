<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
             $table->id();
             $table->uuid('app_id');
             $table->string('reference')->unique();
             $table->bigInteger('amount');
             $table->string('currency')->default('NGN');
             $table->enum('status', ['pending', 'successful', 'failed'])->default('pending');
             $table->string('channel')->nullable();
             $table->timestamp('paid_at')->nullable();
             $table->json('gateway_response')->nullable();
             $table->json('raw_payload')->nullable();
             $table->timestamps();

             $table->index(['app_id', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
