<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_uom_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name', 128);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_uoms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('uom_category_id')->constrained('inventory_uom_categories')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('symbol', 32)->nullable();
            // ratio relative to category reference UoM (reference UoM has ratio = 1)
            $table->decimal('ratio', 14, 6)->default(1);
            $table->decimal('rounding', 14, 6)->default(0.01);
            // reference | bigger | smaller
            $table->string('uom_type', 16)->default('reference');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['uom_category_id', 'active']);
        });

        Schema::create('inventory_product_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_product_categories')->nullOnDelete();
            $table->string('name', 255);
            $table->string('complete_name', 512)->nullable();
            // fifo | lifo | fefo | closest_location
            $table->string('removal_strategy', 32)->default('fifo');
            // standard_price | average_cost | fifo
            $table->string('costing_method', 32)->default('standard_price');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('inventory_product_categories')->nullOnDelete();
            $table->foreignId('uom_id')->constrained('inventory_uoms')->restrictOnDelete();
            $table->foreignId('uom_po_id')->constrained('inventory_uoms')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('internal_reference', 128)->nullable();
            $table->string('barcode', 128)->nullable();
            $table->text('description')->nullable();
            $table->text('description_picking')->nullable();
            // storable | consumable | service
            $table->string('product_type', 24)->default('storable');
            // none | lot | serial
            $table->string('tracking', 16)->default('none');
            $table->decimal('cost', 18, 4)->default(0);
            $table->decimal('sale_price', 18, 4)->default(0);
            $table->decimal('weight', 14, 4)->nullable();
            $table->decimal('volume', 14, 4)->nullable();
            $table->string('image_uuid', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'active']);
            $table->index(['product_type', 'active']);
            $table->index('barcode');
        });

        Schema::create('inventory_product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('partner_name', 255)->nullable();
            $table->string('partner_product_name', 255)->nullable();
            $table->string('partner_product_code', 128)->nullable();
            $table->decimal('min_qty', 14, 4)->default(0);
            $table->decimal('price', 18, 4)->default(0);
            $table->unsignedSmallInteger('delay')->default(1);
            $table->foreignId('currency_id')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_suppliers');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('inventory_product_categories');
        Schema::dropIfExists('inventory_uoms');
        Schema::dropIfExists('inventory_uom_categories');
    }
};
