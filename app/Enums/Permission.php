<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every permission the app checks. Policies reference these cases, never raw
 * strings, so a typo is a compile-time error instead of a silent "deny".
 */
enum Permission: string
{
    case ViewUsers = 'users.view';
    case ManageUsers = 'users.manage';

    case ViewClients = 'clients.view';
    case ManageClients = 'clients.manage';

    // Sites, their location tree and their coverage.
    case ViewSites = 'sites.view';
    case ManageSites = 'sites.manage';

    case ViewCategories = 'categories.view';
    case ManageCategories = 'categories.manage';

    case ViewSlaPolicies = 'sla_policies.view';
    case ManageSlaPolicies = 'sla_policies.manage';
}
