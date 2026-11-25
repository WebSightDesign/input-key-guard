<?php

namespace Wsd\InputKeyGuard\Tests;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use Wsd\InputKeyGuard\InputKeyGuardServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [InputKeyGuardServiceProvider::class];
    }

    protected function defineRoutes($router)
    {
        Route::match(['get', 'post'], '/echo', function (Request $request) {
            return response()->json(['all' => $request->all()]);
        })->middleware([TrimStrings::class, ConvertEmptyStringsToNull::class]);
    }
}
