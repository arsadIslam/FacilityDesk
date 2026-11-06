<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * From `php artisan app:benchmark` on 50k work orders (see README):
 *
 * - (tenant_id, created_at): the default newest-first list was a filesort
 *   over every row of the tenant; now it reads 20 rows off the index.
 * - (tenant_id, resolved_at / closed_at / responded_at): dashboard and
 *   report period counts were scanning the whole tenant.
 * - FULLTEXT(title) on MySQL: title search used LIKE '%…%', a full scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'resolved_at']);
            $table->index(['tenant_id', 'closed_at']);
            $table->index(['tenant_id', 'responded_at']);

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->dropFullText(['title']);
            }

            $table->dropIndex(['tenant_id', 'responded_at']);
            $table->dropIndex(['tenant_id', 'closed_at']);
            $table->dropIndex(['tenant_id', 'resolved_at']);
            $table->dropIndex(['tenant_id', 'created_at']);
        });
    }
};
