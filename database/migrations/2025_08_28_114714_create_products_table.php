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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            // Identity
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->index();

            // Types & statuses (use string for portability; allowed values: simple, variable, variation, external, grouped)
            $table->string('type', 20)->default('simple')->index();
            // allowed: draft, pending, private, publish
            $table->string('status', 20)->default('draft')->index();

            // Pricing
            $table->decimal('regular_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamp('sale_start_at')->nullable();
            $table->timestamp('sale_end_at')->nullable();

            // Inventory
            // allowed: instock, outofstock, onbackorder
            $table->string('stock_status', 20)->default('instock')->index();
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->boolean('manage_stock')->default(false);

            // Physical (optional, useful for sync)
            $table->decimal('weight', 10, 3)->nullable();
            // Store dimensions as JSON: { "length": "...", "width": "...", "height": "..." }
            $table->json('dimensions')->nullable();

            // Content
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();

            // Media
            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable();

            // Taxonomy & meta (can be normalized later)
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            // For variable parents: attribute map; For variations: chosen combination
            $table->json('attributes')->nullable();
            // For variable parents: optionally cache generated variations data locally
            $table->json('variations')->nullable();

            // External product fields
            $table->string('external_url')->nullable();
            $table->string('button_text')->nullable();

            // Sync fields with WooCommerce
            $table->unsignedBigInteger('remote_wp_id')->nullable()->index();
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            // Helpful composite indexes
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
