<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class DataExplorerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        AboutCommand::add('Laravel Data Explorer', fn (): array => ['Version' => DataExplorer::version()]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DataExplorerCommand::class,
            ]);
        }
    }
}
