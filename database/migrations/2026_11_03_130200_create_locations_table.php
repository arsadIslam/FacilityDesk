<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('site_id')->constrained();
            $table->foreignId('parent_id')->nullable()->constrained('locations');
            $table->string('type', 20);
            $table->string('name', 120);
            // Cached breadcrumb, e.g. "Block A / 3rd Floor / Room 304".
            $table->string('path', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'site_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
