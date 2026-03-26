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
        $column = (string) config('fleet_idp.email_sign_in.user_enabled_attribute', 'email_code_login_enabled');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->boolean($column)->default(false);
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
        $column = (string) config('fleet_idp.email_sign_in.user_enabled_attribute', 'email_code_login_enabled');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropColumn($column);
        });
    }
};
