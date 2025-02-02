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
        (new DataExplorer($this->option('connection')))->prompt();
    }
}
