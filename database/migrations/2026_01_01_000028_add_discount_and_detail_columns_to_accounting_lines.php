<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_move_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('account_move_lines', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('partner_id')->constrained('inventory_products')->nullOnDelete();
            }
            if (!Schema::hasColumn('account_move_lines', 'uom_id')) {
                $table->foreignId('uom_id')->nullable()->after('product_id')->constrained('inventory_uoms')->nullOnDelete();
            }
            if (!Schema::hasColumn('account_move_lines', 'tax_line_id')) {
                $table->foreignId('tax_line_id')->nullable()->after('uom_id')->constrained('account_taxes')->nullOnDelete();
            }
            if (!Schema::hasColumn('account_move_lines', 'tax_base_amount')) {
                $table->decimal('tax_base_amount', 18, 4)->default(0)->after('tax_line_id');
            }
            if (!Schema::hasColumn('account_move_lines', 'discount')) {
                $table->decimal('discount', 5, 2)->default(0)->after('tax_base_amount');
            }
            if (!Schema::hasColumn('account_move_lines', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_move_lines', function (Blueprint $table) {
            if (Schema::hasColumn('account_move_lines', 'discount')) {
                $table->dropColumn('discount');
            }
        });
    }
};
