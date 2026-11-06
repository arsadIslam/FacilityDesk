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

    case ViewAssets = 'assets.view';
    case ManageAssets = 'assets.manage';

    case ViewSlaPolicies = 'sla_policies.view';
    case ManageSlaPolicies = 'sla_policies.manage';

    // Without this, a user only sees work orders they raised, are assigned
    // to, or (Client Users) that belong to their client.
    case ViewAllWorkOrders = 'work_orders.view_all';
    case CreateWorkOrders = 'work_orders.create';
    case AssignWorkOrders = 'work_orders.assign';
    // Start, hold and resolve. Technicians: only jobs assigned to them.
    case WorkOnWorkOrders = 'work_orders.work';
    // Confirm (close) or reopen a resolved work order.
    case CloseWorkOrders = 'work_orders.close';
    case CancelWorkOrders = 'work_orders.cancel';
    // Read and write comments hidden from Client Users.
    case InternalComments = 'work_orders.internal_comments';
}
