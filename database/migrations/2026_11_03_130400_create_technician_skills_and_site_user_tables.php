<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_skills', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->primary(['user_id', 'category_id']);
            $table->index(['tenant_id', 'category_id']);
        });

        Schema::create('site_user', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['site_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');
        Schema::dropIfExists('technician_skills');
    }
};
