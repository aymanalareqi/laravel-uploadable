<?php

namespace Alareqi\LaravelUploadable;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Alareqi\LaravelUploadable\Commands\LaravelUploadableCommand;

class LaravelUploadableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-uploadable');
    }
}
