<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Nullable: platform (Super Admin) events have no tenant.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // First day of the reported month, in the tenant's timezone.
            $table->date('month');
            $table->string('status', 20)->default('pending');
            $table->string('batch_id', 36)->nullable()->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('xlsx_path')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
