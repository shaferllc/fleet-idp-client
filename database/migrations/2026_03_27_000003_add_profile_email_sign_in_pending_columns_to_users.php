<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $modelClass = (string) config('fleet_idp.user_model', 'App\\Models\\User');
        if (! class_exists($modelClass)) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'magic_link_sign_in_pending_token_hash')) {
                $blueprint->string('magic_link_sign_in_pending_token_hash', 64)->nullable();
            }
            if (! Schema::hasColumn($table, 'magic_link_sign_in_pending_expires_at')) {
                $blueprint->timestamp('magic_link_sign_in_pending_expires_at')->nullable();
            }
            if (! Schema::hasColumn($table, 'email_code_sign_in_pending_token_hash')) {
                $blueprint->string('email_code_sign_in_pending_token_hash', 64)->nullable();
            }
            if (! Schema::hasColumn($table, 'email_code_sign_in_pending_expires_at')) {
                $blueprint->timestamp('email_code_sign_in_pending_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $modelClass = (string) config('fleet_idp.user_model', 'App\\Models\\User');
        if (! class_exists($modelClass)) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return;
        }

        $toDrop = array_values(array_filter([
            Schema::hasColumn($table, 'magic_link_sign_in_pending_token_hash') ? 'magic_link_sign_in_pending_token_hash' : null,
            Schema::hasColumn($table, 'magic_link_sign_in_pending_expires_at') ? 'magic_link_sign_in_pending_expires_at' : null,
            Schema::hasColumn($table, 'email_code_sign_in_pending_token_hash') ? 'email_code_sign_in_pending_token_hash' : null,
            Schema::hasColumn($table, 'email_code_sign_in_pending_expires_at') ? 'email_code_sign_in_pending_expires_at' : null,
        ]));

        if ($toDrop !== []) {
            Schema::table($table, function (Blueprint $blueprint) use ($toDrop): void {
                $blueprint->dropColumn($toDrop);
            });
        }
    }
};
