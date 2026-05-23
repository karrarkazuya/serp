<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('account_type', 64);
            $table->string('internal_type', 32)->default('other');
            $table->string('currency', 10)->nullable();
            $table->boolean('reconcile')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'account_type']);
            $table->index(['company_id', 'active']);
        });

        Schema::create('account_journals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('default_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('suspense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 16);
            $table->string('name', 128);
            $table->string('type', 16);
            $table->string('currency', 10)->nullable();
            $table->string('sequence_prefix', 32)->default('');
            $table->unsignedInteger('sequence_next_number')->default(1);
            $table->unsignedTinyInteger('sequence_padding')->default(4);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'active']);
        });

        Schema::create('account_moves', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('account_journals')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('reversed_move_id')->nullable()->constrained('account_moves')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 64)->nullable();
            $table->string('ref', 128)->nullable();
            $table->date('date');
            $table->string('state', 16)->default('draft');
            $table->string('payment_state', 16)->default('not_paid');
            $table->string('move_type', 24)->default('entry');
            $table->string('currency', 10)->nullable();
            $table->decimal('amount_total', 18, 4)->default(0);
            $table->text('narration')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'state']);
            $table->index(['company_id', 'date']);
            $table->index(['journal_id', 'state']);
        });

        Schema::create('account_move_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('move_id')->constrained('account_moves')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('account_journals')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->date('date');
            $table->string('state', 16)->default('draft');
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->string('currency', 10)->nullable();
            $table->decimal('amount_currency', 18, 4)->default(0);
            $table->unsignedInteger('sequence')->default(10);
            $table->timestamps();

            $table->index('move_id');
            $table->index(['account_id', 'state']);
            $table->index(['company_id', 'date']);
            $table->index(['journal_id', 'state']);
        });

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

    public function down(): void
    {
        Schema::dropIfExists('account_partial_reconciles');
        Schema::dropIfExists('account_payments');
        Schema::dropIfExists('account_move_lines');
        Schema::dropIfExists('account_moves');
        Schema::dropIfExists('account_journals');
        Schema::dropIfExists('accounts');
    }
};
