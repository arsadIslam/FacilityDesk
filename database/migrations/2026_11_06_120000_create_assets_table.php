<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('site_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('category_id')->constrained();
            $table->string('name', 150);
            $table->string('code', 40);
            // ULID printed in the QR code; unguessable, and unique across tenants.
            $table->char('qr_token', 26)->unique();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->date('installed_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('status', 20)->default('working');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'site_id']);
            $table->index(['tenant_id', 'location_id']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });

        Schema::dropIfExists('assets');
    }
};
