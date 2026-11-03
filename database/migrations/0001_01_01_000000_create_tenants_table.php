<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 60)->unique();
            $table->string('email', 150);
            $table->string('phone', 20)->nullable();
            $table->string('plan', 20)->default('trial');
            $table->string('status', 20)->default('active')->index();
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->string('logo_path')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
