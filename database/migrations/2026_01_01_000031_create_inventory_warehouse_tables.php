<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Locations must exist before warehouses (FK to locations)
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 255);
            $table->string('complete_name', 512)->nullable();
            // supplier | view | internal | customer | inventory | production | transit
            $table->string('usage', 24)->default('internal');
            // fifo | lifo | fefo | closest_location
            $table->string('removal_strategy', 32)->nullable();
            $table->boolean('scrap_location')->default(false);
            $table->boolean('return_location')->default(false);
            $table->string('barcode', 64)->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedSmallInteger('posx')->default(0);
            $table->unsignedSmallInteger('posy')->default(0);
            $table->unsignedSmallInteger('posz')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'usage', 'active']);
            $table->index(['parent_id', 'active']);
        });

        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
            // References to auto-created locations
            $table->foreignId('lot_stock_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('wh_input_stock_loc_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('wh_output_stock_loc_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('wh_pack_stock_loc_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('view_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 255);
            $table->string('short_name', 8);
            // one_step | two_steps | three_steps
            $table->string('reception_steps', 16)->default('one_step');
            $table->string('delivery_steps', 16)->default('one_step');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'short_name']);
            $table->index(['company_id', 'active']);
        });

        Schema::create('inventory_operation_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->foreignId('default_location_src_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('default_location_dest_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('return_picking_type_id')->nullable()->constrained('inventory_operation_types')->nullOnDelete();
            $table->string('name', 255);
            // incoming | outgoing | internal
            $table->string('code', 16)->default('internal');
            $table->boolean('use_existing_lots')->default(true);
            $table->boolean('use_create_lots')->default(true);
            $table->boolean('show_entire_packs')->default(false);
            $table->string('sequence_prefix', 32)->default('');
            $table->unsignedInteger('sequence_next_number')->default(1);
            $table->unsignedTinyInteger('sequence_padding')->default(5);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'code', 'active']);
            $table->index(['warehouse_id', 'active']);
        });

        Schema::create('inventory_routes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('supplied_wh_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->foreignId('supplier_wh_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->string('name', 255);
            $table->unsignedSmallInteger('sequence')->default(10);
            $table->boolean('product_category_selectable')->default(false);
            $table->boolean('product_selectable')->default(true);
            $table->boolean('warehouse_selectable')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_route_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('route_id')->constrained('inventory_routes')->cascadeOnDelete();
            $table->foreignId('operation_type_id')->nullable()->constrained('inventory_operation_types')->nullOnDelete();
            $table->foreignId('location_src_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('location_dest_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 255);
            // push | pull
            $table->string('action', 16)->default('pull');
            $table->unsignedSmallInteger('sequence')->default(10);
            $table->unsignedSmallInteger('delay')->default(0);
            // none | propagate | fixed
            $table->string('group_propagation_option', 16)->default('propagate');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['route_id', 'active']);
        });

        Schema::create('inventory_putaway_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('fixed_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('inventory_product_categories')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(10);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'active']);
        });

        // Pivot: products ↔ routes
        Schema::create('inventory_product_routes', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('inventory_routes')->cascadeOnDelete();
            $table->primary(['product_id', 'route_id']);
        });

        // Pivot: product_categories ↔ routes
        Schema::create('inventory_category_routes', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('inventory_product_categories')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('inventory_routes')->cascadeOnDelete();
            $table->primary(['category_id', 'route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_category_routes');
        Schema::dropIfExists('inventory_product_routes');
        Schema::dropIfExists('inventory_putaway_rules');
        Schema::dropIfExists('inventory_route_rules');
        Schema::dropIfExists('inventory_routes');
        Schema::dropIfExists('inventory_operation_types');
        Schema::dropIfExists('inventory_warehouses');
        Schema::dropIfExists('inventory_locations');
    }
};
