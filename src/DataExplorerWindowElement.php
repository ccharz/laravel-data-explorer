<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer;

enum DataExplorerWindowElement: string
{
    case TABLE_SELECTION = 'table_selection';

    case RESULT = 'result';

    case SQL_INPUT_POPUP = 'sql_input_popup';

    case TABLE_SELECTION_POPUP = 'table_selection_popup';
}
