<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the global unique index on contact_phones.phone with a non-unique
 * lookup index. Phone uniqueness is now enforced in the app layer:
 *   - within a single contact's phone list (via 'distinct' validation), and
 *   - within the actor's active companies (via a custom unique-where-join check).
 *
 * Why drop the DB-level unique:
 *   - It collided with the soft-delete column: once a phone was deleted (soft),
 *     re-saving the contact with the same phone hit the constraint.
 *   - It leaked across tenants: a user in company B could probe whether a phone
 *     was already in use by company A (no result vs. constraint violation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_phones', function (Blueprint $table) {
            $table->dropUnique('contact_phones_phone_unique');
            $table->index('phone', 'contact_phones_phone_index');
        });
    }

    public function down(): void
    {
        Schema::table('contact_phones', function (Blueprint $table) {
            $table->dropIndex('contact_phones_phone_index');
            $table->unique('phone', 'contact_phones_phone_unique');
        });
    }
};
