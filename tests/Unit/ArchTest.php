<?php

arch('application code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('controllers hold no persistence logic')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\Eloquent\Builder']);

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('actions are final and expose handle()')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveMethod('handle');
