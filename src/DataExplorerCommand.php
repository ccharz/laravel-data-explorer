<?php

namespace Ccharz\DataExplorer;

use Illuminate\Console\Command;

class DataExplorerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data-explorer {--connection=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Data Explorer TUI';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $connection = is_string($this->option('connection')) && $this->option('connection') !== ''
            ? $this->option('connection')
            : null;

        (new DataExplorer($connection))->prompt();
    }
}
