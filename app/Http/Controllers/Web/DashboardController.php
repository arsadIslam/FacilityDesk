<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\View\View;

/**
 * Placeholder tenant home; replaced by the real dashboard in Phase 7.
 */
final class DashboardController extends Controller
{
    public function __invoke(TenantContext $context): View
    {
        return view('dashboard', ['tenant' => $context->tenant()]);
    }
}
