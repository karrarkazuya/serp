<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_taxes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('amount_type')->default('percent'); // percent | fixed
            $table->decimal('amount', 16, 4)->default(0);
            $table->string('type_tax_use')->default('sale'); // sale | purchase | none
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('description')->nullable();
            $table->boolean('include_base_amount')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Pivot: which taxes apply to which journal entry lines (product lines only, not tax lines)
        Schema::create('account_move_line_taxes', function (Blueprint $table) {
            $table->foreignId('account_move_line_id')->constrained('account_move_lines')->cascadeOnDelete();
            $table->foreignId('account_tax_id')->constrained('account_taxes')->cascadeOnDelete();
            $table->primary(['account_move_line_id', 'account_tax_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_move_line_taxes');
        Schema::dropIfExists('account_taxes');
    }
};
