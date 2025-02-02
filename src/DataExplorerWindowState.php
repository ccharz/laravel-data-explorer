<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer;

class DataExplorerWindowState
{
    /**
     * @param  null|string[]  $table_selection_tables
     * @return void
     */
    public function __construct(
        public DataExplorerWindowElement $focus = DataExplorerWindowElement::TABLE_SELECTION,

        public ?DataExplorerTableData $currentTable = null,

        public ?DataExplorerWindowElement $popup = null,

        public ?array $table_selection_tables = null,

        public int $table_selection_index = -1,

        public int $selected_result_row = -1,

        public int $selected_result_column = 0,

        public int $cursor_position = 0,

        public int $selected_popup_row = 0,

        public string $popup_background = '',

        public string $input = '',

        public bool $is_loading_table_selection = true,

        public bool $is_loading_result = false,
    ) {}
}
