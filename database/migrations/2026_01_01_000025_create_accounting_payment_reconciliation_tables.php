<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_payments')) {
            Schema::create('account_payments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('journal_id')->constrained('account_journals')->cascadeOnDelete();
                $table->foreignId('move_id')->constrained('account_moves')->cascadeOnDelete();
                $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->foreignId('paired_document_id')->nullable()->constrained('account_moves')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('payment_type', 16);
                $table->date('date');
                $table->decimal('amount', 18, 4);
                $table->string('currency', 10)->nullable();
                $table->string('memo', 255)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'date']);
                $table->index(['paired_document_id']);
            });
        }

        if (!Schema::hasTable('account_partial_reconciles')) {
            Schema::create('account_partial_reconciles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('debit_move_line_id')->constrained('account_move_lines')->cascadeOnDelete();
                $table->foreignId('credit_move_line_id')->constrained('account_move_lines')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 18, 4);
                $table->date('date');
                $table->timestamps();

                $table->index(['debit_move_line_id']);
                $table->index(['credit_move_line_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_partial_reconciles');
        Schema::dropIfExists('account_payments');
    }
};
