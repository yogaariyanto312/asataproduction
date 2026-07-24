<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 80)->nullable()->after('username');        // @qc.yoga
            $table->text('bio')->nullable()->after('handle');                    // Bio singkat
            $table->string('link_instagram', 255)->nullable()->after('bio');
            $table->string('link_github', 255)->nullable()->after('link_instagram');
            $table->string('link_portfolio', 255)->nullable()->after('link_github');
            $table->string('link_email', 150)->nullable()->after('link_portfolio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['handle', 'bio', 'link_instagram', 'link_github', 'link_portfolio', 'link_email']);
        });
    }
};
