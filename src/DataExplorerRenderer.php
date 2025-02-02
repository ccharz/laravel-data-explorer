<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer;

use Ccharz\DataExplorer\Concerns\DrawsPopups;
use Ccharz\DataExplorer\Concerns\DrawsScrollableBoxes;
use Chewie\Concerns\DrawsHotkeys;
use Illuminate\Support\Arr;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Concerns\Truncation;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use Laravel\Prompts\Themes\Default\Renderer;
use Override;

class DataExplorerRenderer extends Renderer
{
    use Colors;
    use DrawsHotkeys;
    use DrawsPopups;
    use DrawsScrollableBoxes;
    use InteractsWithStrings;
    use Truncation;

    private int $width;

    private int $height;

    private DataExplorer $dataExplorer;

    public function __invoke(DataExplorer $dataExplorer): self
    {
        $this->dataExplorer = $dataExplorer;

        [$this->width, $this->height] = $dataExplorer->getDimensions();

        $dataExplorer->setPageHeight($this->height - 3);

        $windowState = $dataExplorer->windowState;

        if ($windowState->popup instanceof DataExplorerWindowElement) {
            $this->output = match ($windowState->popup) {
                DataExplorerWindowElement::SQL_INPUT_POPUP => $this->popup(
                    $windowState->popup_background,
                    (int) floor($this->width * 0.75),
                    fn (int $popup_width): array => $this->scrollableBox(
                        width: $popup_width,
                        height: 3,
                        lines: fn (int $width): array => $dataExplorer->inputWithCursor($width),
                        title: 'SQL',
                        border_color: $this->getColor(true),
                    )
                ),

                DataExplorerWindowElement::TABLE_SELECTION_POPUP => $this->popup(
                    $windowState->popup_background,
                    (int) floor($this->width * 0.33),
                    fn (int $popup_width): array => $this->scrollableBox(
                        width: $popup_width,
                        height: 10,
                        lines: fn (int $width, string $color, int $current_line): array => Arr::map(
                            $windowState->table_selection_tables,
                            fn (string $table, int $index): string => $index === $current_line
                                ? $this->inverse($this->fixedWidth($table, $width))
                                : $this->fixedWidth($table, $width)
                        ),
                        current_line: $windowState->selected_popup_row,
                        title: 'Jump to table',
                        border_color: $this->getColor(true),
                    )
                ),

                default => null,
            };

            return $this;
        }

        $column_left_width = (int) floor($this->width * 0.2);

        $columns = [
            $this->renderTableSelection(
                $column_left_width,
                $this->height - 1
            ),
            $this->renderResultWindow(
                $this->width - $column_left_width,
                $this->height - 1
            ),
        ];

        $hotkeys = $this->renderHotkeys();

        $version = 'v'.DataExplorer::version();

        $this->output = collect(array_shift($columns))
            ->zip(...$columns)
            ->map(fn ($lines) => $lines->implode(''))
            ->push($hotkeys.str_repeat(' ', $this->width - mb_strwidth($this->stripEscapeSequences($version)) - mb_strwidth($this->stripEscapeSequences($hotkeys))).$this->dim($version))
            ->join(PHP_EOL);

        return $this;
    }

    /**
     * @return string[]
     */
    private function renderResultWindow(int $width, int $height): array
    {
        $windowState = $this->dataExplorer->windowState;

        $title = match (true) {
            $windowState->table_selection_index > -1 => $windowState->table_selection_tables[$windowState->table_selection_index] ?? '',
            default => 'Table Overview'
        };

        $color = $this->getColor($windowState->focus === DataExplorerWindowElement::RESULT);

        $output = $this->scrollableBox(
            width: $width,
            height: $height,
            lines: match (true) {
                $windowState->is_loading_result => ['Loading...'],
                $windowState->currentTable instanceof DataExplorerTableData => fn (int $width): array => $this->renderTable($windowState, $width),
                default => []
            },
            current_line: $windowState->selected_result_row,
            title: $title,
            footer: '',
            fixed_rows: 2,
            border_color: $color,
        );

        $footer_left = isset($windowState->table_selection_tables[$windowState->table_selection_index])
            ? $this->{$color}('┤').
                ($windowState->currentTable?->total !== null
                    ? $this->inverse(' Data ')
                    : $this->dim(' Data '))
                    .'│'
                .($windowState->currentTable?->total === null
                    ? $this->inverse(' Structure ')
                    : $this->dim(' Structure '))
                    .$this->{$color}('├')
            : '';

        $footer_right = isset($windowState->table_selection_tables[$windowState->table_selection_index])
            ? ' Page '.($windowState->currentTable->page).' of '.$windowState->currentTable->last_page
            : '';

        if ($footer_left !== '' || $footer_right !== '') {
            $output[count($output) - 1] = $this->{$color}(' └─').$this->pad($footer_left, $width - mb_strwidth($footer_right) - 4, $this->{$color}('─')).$footer_right.$this->{$color}('┘');
        }

        return $output;
    }

    /**
     * @return string[]
     */
    private function renderTableSelection(int $width, int $height): array
    {
        return $this->scrollableBox(
            width: $width,
            height: $height,
            lines: $this->dataExplorer->windowState->is_loading_table_selection
                ? [' Loading... ']
                : fn (int $width): array => Arr::map(
                    $this->dataExplorer->windowState->table_selection_tables ?? [],
                    fn (string $table, int $index): string => $index === $this->dataExplorer->windowState->table_selection_index
                        ? $this->inverse($this->fixedWidth($table, $width))
                        : $this->fixedWidth($table, $width)
                ),
            current_line: $this->dataExplorer->windowState->table_selection_index,
            title: 'Tables',
            border_color: $this->getColor(
                $this->dataExplorer->windowState->focus === DataExplorerWindowElement::TABLE_SELECTION
            ),
        );
    }

    /**
     * @return string[]
     */
    private function renderTable(DataExplorerWindowState $windowState, int $width): array
    {
        $table = $windowState->currentTable;

        /* Calculate Maximum Column Width */
        $column_widths = [];

        foreach ($table->columns as $column_index => $column) {
            $current_column_width = mb_strwidth($column);
            $column_widths[$column_index] = min(30, max($column_widths[$column_index] ?? 0, $current_column_width));
        }

        foreach ($table->rows as $row) {
            foreach ($row as $column_index => $value) {
                $current_column_width = mb_strwidth((string) $value);
                $column_widths[$column_index] = min(30, max($column_widths[$column_index] ?? 0, $current_column_width));
            }
        }

        /** Draw Header */
        $lines = [
            $this->pad(implode('│ ', Arr::map($table->columns, fn (string $column, int $column_index): string => $this->pad($column, $column_widths[$column_index] + 1))).'│', $width, ' '),
            $this->pad(implode('┼─', Arr::map($table->columns, fn (string $column, int $column_index): string => $this->pad('', $column_widths[$column_index] + 1, '─'))).'┼', $width, '─'),
        ];

        foreach ($table->rows as $row) {
            $line = '';
            foreach ($row as $column_index => $value) {
                $line .= ($column_index > 0 ? ' │ ' : '').$this->fixedWidth((string) $value, $column_widths[$column_index]);
            }

            $lines[] = $line.' │';
        }

        $line_offset = 0;
        $column_offset = 0;

        $line_width = array_sum($column_widths) + ((count($table->columns) - 1) * 4) + 3;

        for ($i = 0; $i < $windowState->selected_result_column; $i++) {
            $column_offset += $column_widths[$i] + 3;

            if ($line_width - $line_offset > $width) {
                $line_offset = $column_offset;
            }
        }

        $color = $this->getColor($windowState->focus === DataExplorerWindowElement::RESULT);

        foreach ($lines as $line_index => $line) {
            $line = $this->pad(mb_substr($line, $line_offset, $width), $width, $line_index === 1 ? '─' : ' ');

            if ($windowState->focus === DataExplorerWindowElement::RESULT
                && $line_index !== 1
                && (($line_index === 0 && $windowState->selected_result_row === -1)
                    || ($line_index - 2 === $windowState->selected_result_row)
                )
            ) {
                $selection_start = $column_offset - $line_offset;
                $selection_width = $column_widths[$windowState->selected_result_column];
                $line = ($selection_start > 0 ? mb_substr($line, 0, $selection_start) : '')
                    .$this->inverse(mb_substr($line, $selection_start, $selection_width))
                    .mb_substr($line, $selection_start + $selection_width);
            }

            $line = $line_index === 1 ? $this->$color($line) : str_replace('│ ', $this->$color('│ '), $line);

            $lines[$line_index] = $line;
        }

        return $lines;
    }

    private function renderHotkeys(): string
    {
        $hotkeys = [
            'q' => 'Quit',
            '<TAB>' => 'Toggle Focus',
            's' => 'SQL',
            'j' => 'Select Table',
            ...($this->dataExplorer->windowState->table_selection_index >= 0 && $this->dataExplorer->windowState->currentTable !== null
                ? ['t' => $this->dataExplorer->windowState->currentTable->total !== null
                    ? 'Show Structure'
                    : 'Show Data',
                ]
                : []
            ),
            ...($this->dataExplorer->windowState->currentTable->last_page !== null && $this->dataExplorer->windowState->currentTable->last_page > 1
            ? [
                'Pagination',
                'f' => 'First',
                'p' => 'Previous',
                'n' => 'Next',
                'l' => 'Last',
            ]
                : []
            ),
        ];

        return '  '.collect($hotkeys)
            ->map(fn (string $description, string $key): string => is_numeric($key)
                ? $this->bold('   '.$description)
                : $this->dim($key).' '.$description
            )
            ->join(' ');
    }

    private function getColor(bool $active): string
    {
        return $active ? 'cyan' : 'dim';
    }

    #[Override]
    public function __toString(): string
    {
        return rtrim($this->output, "\n");
    }
}
