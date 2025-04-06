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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->string('company_name')->nullable();
            $table->string('site_url')->nullable();
            $table->string('consumer_secret')->nullable();
            $table->string('consumer_key')->nullable();
            $table->string('app_username')->nullable();
            $table->string('app_password')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->float('annual_price')->nullable();
            $table->string('notes')->nullable();
            $table->integer('status')->default(0); // 1: active, 1: not active

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
