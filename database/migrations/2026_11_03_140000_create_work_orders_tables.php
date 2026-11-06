<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One counter row per tenant per year, incremented under a row lock.
        Schema::create('work_order_sequences', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['tenant_id', 'year']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('number', 30);
            $table->foreignId('site_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('category_id')->constrained();
            // asset_id is added with the assets table in Phase 5.
            $table->foreignId('raised_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('priority', 20);
            $table->string('status', 20)->default('open');
            $table->string('source', 20)->default('web');
            // DATETIME, not TIMESTAMP: MySQL/MariaDB without explicit_defaults_for_timestamp
            // give a second NOT NULL TIMESTAMP an invalid implicit default. Values are UTC.
            $table->dateTime('response_due_at');
            $table->dateTime('resolution_due_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('hold_minutes')->default(0);
            $table->boolean('is_response_breached')->default(false);
            $table->boolean('is_resolution_breached')->default(false);
            $table->timestamp('breach_notified_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedTinyInteger('client_rating')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['tenant_id', 'assigned_to', 'status']);
            $table->index(['tenant_id', 'site_id', 'created_at']);
            // Cross-tenant SLA breach scan (Phase 5).
            $table->index(['status', 'resolution_due_at']);
        });

        Schema::create('work_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('work_order_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            // Hidden from Client Users.
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_comments');
        Schema::dropIfExists('work_order_status_histories');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('work_order_sequences');
    }
};
