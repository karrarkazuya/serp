<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('ref', 128)->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('use_date')->nullable();
            $table->date('removal_date')->nullable();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'name']);
            $table->index(['product_id', 'active']);
        });

        Schema::create('inventory_pickings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('operation_type_id')->constrained('inventory_operation_types')->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('location_src_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('location_dest_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('origin_picking_id')->nullable()->constrained('inventory_pickings')->nullOnDelete();
            $table->string('name', 64)->nullable();
            $table->string('origin', 128)->nullable();
            $table->string('note', 512)->nullable();
            // draft | confirmed | assigned | done | cancelled
            $table->string('state', 16)->default('draft');
            $table->dateTime('scheduled_date')->nullable();
            $table->dateTime('date_done')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'state']);
            $table->index(['operation_type_id', 'state']);
        });

        Schema::create('inventory_moves', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('picking_id')->nullable()->constrained('inventory_pickings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('inventory_uoms')->restrictOnDelete();
            $table->foreignId('location_src_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('location_dest_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('origin_returned_move_id')->nullable()->constrained('inventory_moves')->nullOnDelete();
            $table->string('name', 255);
            $table->string('origin', 128)->nullable();
            $table->decimal('product_qty', 14, 4)->default(0);
            $table->decimal('qty_done', 14, 4)->default(0);
            $table->decimal('reserved_qty', 14, 4)->default(0);
            // draft | confirmed | assigned | partially_available | done | cancelled
            $table->string('state', 24)->default('draft');
            $table->unsignedSmallInteger('sequence')->default(10);
            $table->date('date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['picking_id', 'state']);
            $table->index(['product_id', 'state']);
            $table->index(['company_id', 'state']);
        });

        Schema::create('inventory_move_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('move_id')->constrained('inventory_moves')->cascadeOnDelete();
            $table->foreignId('picking_id')->nullable()->constrained('inventory_pickings')->nullOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('inventory_uoms')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('location_dest_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->string('lot_name', 128)->nullable();
            $table->decimal('reserved_qty', 14, 4)->default(0);
            $table->decimal('qty_done', 14, 4)->default(0);
            $table->date('date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['move_id']);
            $table->index(['picking_id']);
            $table->index(['product_id', 'lot_id']);
        });

        Schema::create('inventory_quants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->decimal('reserved_quantity', 14, 4)->default(0);
            $table->dateTime('in_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'location_id']);
            $table->index(['location_id', 'company_id']);
        });

        Schema::create('inventory_scrap_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('inventory_uoms')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('scrap_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->foreignId('picking_id')->nullable()->constrained('inventory_pickings')->nullOnDelete();
            $table->foreignId('move_id')->nullable()->constrained('inventory_moves')->nullOnDelete();
            $table->string('name', 64)->nullable();
            $table->decimal('scrap_qty', 14, 4)->default(1);
            // draft | done
            $table->string('state', 16)->default('draft');
            $table->string('origin', 128)->nullable();
            $table->date('date_done')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'state']);
            $table->index(['product_id']);
        });

        Schema::create('inventory_reorder_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('inventory_routes')->nullOnDelete();
            $table->decimal('qty_min', 14, 4)->default(0);
            $table->decimal('qty_max', 14, 4)->default(0);
            $table->decimal('qty_multiple', 14, 4)->default(1);
            $table->decimal('qty_on_hand', 14, 4)->default(0);
            $table->decimal('qty_forecast', 14, 4)->default(0);
            $table->unsignedSmallInteger('lead_days')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'active']);
            $table->index(['product_id', 'location_id']);
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 128)->nullable();
            // draft | in_progress | done
            $table->string('state', 16)->default('draft');
            $table->boolean('exhausted')->default(false);
            $table->date('date')->nullable();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'state']);
        });

        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->decimal('inventory_qty', 14, 4)->default(0);
            $table->decimal('theoretical_qty', 14, 4)->default(0);
            $table->decimal('difference_qty', 14, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['adjustment_id']);
            $table->index(['product_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_reorder_rules');
        Schema::dropIfExists('inventory_scrap_orders');
        Schema::dropIfExists('inventory_quants');
        Schema::dropIfExists('inventory_move_lines');
        Schema::dropIfExists('inventory_moves');
        Schema::dropIfExists('inventory_pickings');
        Schema::dropIfExists('inventory_lots');
    }
};
