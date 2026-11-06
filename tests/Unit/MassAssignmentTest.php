<?php

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;

/**
 * @return array<string, class-string<Model>>
 */
function appModels(): array
{
    $models = [];

    foreach (Finder::create()->files()->in(app_path('Models'))->depth(0)->name('*.php') as $file) {
        $class = 'App\\Models\\'.$file->getBasename('.php');

        if (is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $models[class_basename($class)] = $class;
        }
    }

    return $models;
}

it('never lets tenant_id be mass assigned on any model', function () {
    foreach (appModels() as $name => $class) {
        $model = new $class;

        $fullyUnguarded = $model->getGuarded() === [] && $model->getFillable() === [];

        expect($model->isFillable('tenant_id'))->toBeFalse("{$name}::tenant_id is fillable")
            ->and($fullyUnguarded)->toBeFalse("{$name} is fully unguarded");
    }
});

it('only lets requesters fill a work order\'s title and description', function () {
    $workOrder = new WorkOrder;

    expect($workOrder->getFillable())->toBe(['title', 'description']);

    foreach (['tenant_id', 'status', 'assigned_to', 'assigned_by', 'number', 'raised_by', 'response_due_at', 'resolution_due_at', 'is_resolution_breached'] as $column) {
        expect($workOrder->isFillable($column))->toBeFalse("WorkOrder::{$column} is fillable");
    }
});

it('never lets users or assets set their own identity or tokens', function () {
    $user = new User;
    $asset = new Asset;

    expect($user->isFillable('client_id'))->toBeFalse()
        ->and($user->isFillable('email_verified_at'))->toBeFalse()
        ->and($asset->isFillable('qr_token'))->toBeFalse()
        ->and((new Tenant)->isFillable('status'))->toBeFalse()
        ->and((new Tenant)->isFillable('plan'))->toBeFalse();
});
