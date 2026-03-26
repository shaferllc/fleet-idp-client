<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('fleet_idp.email_sign_in.challenges_table', 'local_email_login_challenges');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->uuid('id')->primary();
            $blueprint->string('email');
            $blueprint->string('code_hash')->nullable();
            $blueprint->string('token_hash', 128)->nullable();
            $blueprint->timestamp('expires_at');
            $blueprint->timestamp('consumed_at')->nullable();
            $blueprint->timestamps();

            $blueprint->unique('email');
            $blueprint->index('token_hash');
        });
    }

    public function down(): void
    {
        $table = (string) config('fleet_idp.email_sign_in.challenges_table', 'local_email_login_challenges');

        Schema::dropIfExists($table);
    }
};
