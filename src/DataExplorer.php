<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer;

use Chewie\Concerns\RegistersRenderers;
use Chewie\Input\KeyPressListener;
use Laravel\Prompts\Concerns\TypedValue;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Override;

class DataExplorer extends Prompt
{
    use RegistersRenderers;
    use TypedValue;

    private int $page_height = 0;

    public int $width;

    public int $height;

    public ?string $last_frame = null;

    public int $cursor_position = 0;

    public DataExplorerWindowState $windowState;

    public static function version(): string
    {
        return '0.0.3';
    }

    public function __construct(private readonly ?string $connection = null)
    {
        $this->registerRenderer(DataExplorerRenderer::class);

        $this->width = $this->terminal()->cols();

        $this->height = $this->terminal()->lines();

        $this->windowState = new DataExplorerWindowState;

        $this->bindDefaultListeners();
        $this->loadTableOverview();
    }

    public function setPageHeight(int $height): self
    {
        $this->page_height = $height;

        return $this;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function getDimensions(): array
    {
        return [
            $this->terminal()->cols(),
            $this->terminal()->lines(),
        ];
    }

    /**
     * @return string[]
     */
    public function inputWithCursor(int $max_width): array
    {
        return [
            $this->addCursor($this->windowState->input, $this->windowState->cursor_position, $max_width),
        ];
    }

    public function quit(): void
    {
        $this->terminal()->exit();
    }

    private function bindDefaultListeners(): self
    {
        KeyPressListener::for($this)
            ->clearExisting()
            ->listenForQuit()
            ->onUp(fn () => $this->previousRow())
            ->onDown(fn () => $this->nextRow())
            ->onLeft(fn () => $this->previousColumn())
            ->onRight(fn () => $this->nextColumn())
            ->on(Key::ESCAPE, fn () => $this->back())
            ->on(Key::TAB, fn () => $this->focusNext())
            ->on(Key::SHIFT_TAB, fn () => $this->focusPrevious())
            ->on(Key::PAGE_UP, fn () => $this->pageUp())
            ->on(Key::PAGE_DOWN, fn () => $this->pageDown())

            ->on('n', fn () => $this->pagination('next'))
            ->on('p', fn () => $this->pagination('previous'))
            ->on('f', fn () => $this->pagination('first'))
            ->on('l', fn () => $this->pagination('last'))

            ->on('j', fn () => $this->showPopup(DataExplorerWindowElement::TABLE_SELECTION_POPUP))
            ->on('s', fn () => $this->showPopup(DataExplorerWindowElement::SQL_INPUT_POPUP))
            ->on('o', fn () => $this->orderByColumn())
            ->on('t', fn () => $this->toggleTableData())
            ->listen();

        return $this;
    }

    private function bindInputListener(): self
    {
        KeyPressListener::for($this)
            ->clearExisting()
            ->listenForQuit()
            ->onUp(fn () => $this->previousRow())
            ->onDown(fn () => $this->nextRow())
            ->on(Key::ESCAPE, fn () => $this->back())
            ->on(Key::ENTER, fn () => $this->submit())
            ->listenToInput($this->windowState->input, $this->windowState->cursor_position)
            ->listen();

        return $this;
    }

    private function showPopup(DataExplorerWindowElement $element): void
    {
        $this->windowState->input = '';
        $this->windowState->selected_popup_row = 0;
        $this->windowState->popup = $element;
        $this->windowState->popup_background = $this->prevFrame;

        $this->bindInputListener();
    }

    private function hidePopup(): void
    {
        $this->windowState->popup = null;
        $this->windowState->popup_background = '';
        $this->windowState->input = '';
        $this->windowState->selected_popup_row = 0;
        $this->windowState->cursor_position = 0;

        $this->bindDefaultListeners();
    }

    private function back(): void
    {
        $this->windowState->popup instanceof DataExplorerWindowElement
            ? $this->hidePopup()
            : $this->loadTableOverview();

        $this->bindDefaultListeners();
    }

    #[Override]
    protected function submit(): void
    {
        switch (true) {
            case $this->windowState->popup === DataExplorerWindowElement::TABLE_SELECTION_POPUP:
                $this->loadTable($this->windowState->selected_popup_row);
                $this->hidePopup();

                break;
            case $this->windowState->popup === DataExplorerWindowElement::SQL_INPUT_POPUP:
                $this->windowState->selected_result_row = -1;
                $this->windowState->selected_result_column = 0;
                $this->windowState->currentTable = DataExplorerTableData::fromQuery(
                    $this->windowState->input,
                    $this->connection
                );
                $this->hidePopup();
                break;
            default: $this->back();
        }
    }

    private function focusNext(): void
    {
        $this->windowState->focus = match ($this->windowState->focus) {
            DataExplorerWindowElement::TABLE_SELECTION => DataExplorerWindowElement::RESULT,
            DataExplorerWindowElement::RESULT => DataExplorerWindowElement::TABLE_SELECTION,
            default => $this->windowState->focus
        };
    }

    private function focusPrevious(): void
    {
        $this->windowState->focus = match ($this->windowState->focus) {
            DataExplorerWindowElement::RESULT => DataExplorerWindowElement::TABLE_SELECTION,
            DataExplorerWindowElement::TABLE_SELECTION => DataExplorerWindowElement::RESULT,
            default => $this->windowState->focus
        };
    }

    private function nextColumn(): void
    {
        match ($this->windowState->focus) {
            DataExplorerWindowElement::TABLE_SELECTION => $this->focusNext(),
            DataExplorerWindowElement::RESULT => $this->windowState->selected_result_column = min(
                $this->windowState->selected_result_column + 1,
                count($this->windowState->currentTable->columns ?? []) - 1
            ),
            default => null,
        };
    }

    private function previousColumn(): void
    {
        match ($this->windowState->focus) {
            DataExplorerWindowElement::RESULT => $this->windowState->selected_result_column === 0
                ? $this->focusPrevious()
                : $this->windowState->selected_result_column -= 1,
            default => null,
        };
    }

    private function nextRow(): void
    {
        switch (true) {
            case $this->windowState->popup === DataExplorerWindowElement::TABLE_SELECTION_POPUP:
                $this->windowState->selected_popup_row = min(
                    count($this->windowState->table_selection_tables) - 1,
                    $this->windowState->selected_popup_row + 1,
                );
                break;
            case $this->windowState->focus === DataExplorerWindowElement::TABLE_SELECTION:
                $this->loadTable(
                    $this->windowState->table_selection_index + 1
                );
                break;
            case $this->windowState->focus === DataExplorerWindowElement::RESULT:
                $this->windowState->selected_result_row = min(
                    count($this->windowState->currentTable->rows) - 1,
                    $this->windowState->selected_result_row + 1
                );
                break;
        }
    }

    private function previousRow(): void
    {
        switch (true) {
            case $this->windowState->popup === DataExplorerWindowElement::TABLE_SELECTION_POPUP:
                $this->windowState->selected_popup_row = max(0, $this->windowState->selected_popup_row - 1);
                break;
            case $this->windowState->focus === DataExplorerWindowElement::TABLE_SELECTION:
                $this->loadTable($this->windowState->table_selection_index - 1);
                break;
            case $this->windowState->focus === DataExplorerWindowElement::RESULT:
                $this->windowState->selected_result_row = max(
                    $this->windowState->selected_result_row - 1,
                    -1
                );

                break;
        }
    }

    private function pageDown(): void
    {
        switch ($this->windowState->focus) {
            case DataExplorerWindowElement::TABLE_SELECTION:
                $this->loadTable((int) min(
                    count($this->windowState->table_selection_tables) - 1,
                    $this->windowState->table_selection_index + $this->page_height
                ));
                break;
            case DataExplorerWindowElement::RESULT:
                $this->windowState->selected_result_row = min(
                    count($this->windowState->currentTable->rows) - 1,
                    $this->windowState->selected_result_row + ($this->page_height - 2)
                );
                break;
        }
    }

    private function pageUp(): void
    {
        switch ($this->windowState->focus) {
            case DataExplorerWindowElement::TABLE_SELECTION:
                $this->loadTable(max(
                    0,
                    $this->windowState->table_selection_index - $this->page_height
                ));
                break;
            case DataExplorerWindowElement::RESULT:
                $this->windowState->selected_result_row = max(
                    0,
                    $this->windowState->selected_result_row - ($this->page_height - 2)
                );

                break;
        }
    }

    private function loadTableOverview(): void
    {
        $this->windowState = new DataExplorerWindowState;
        $this->windowState->currentTable = DataExplorerTableData::overview($this->connection);
        $this->windowState->table_selection_tables = collect($this->windowState->currentTable->rows)
            ->pluck(0)
            ->all();
        $this->windowState->is_loading_table_selection = false;
    }

    private function toggleTableData(): void
    {
        if ($this->windowState->table_selection_index === -1
            || ! $this->windowState->currentTable instanceof DataExplorerTableData
        ) {
            return;
        }

        if ($this->windowState->currentTable->total !== null) {
            $this->windowState->is_loading_result = true;
            $this->windowState->selected_result_row = -1;
            $this->windowState->selected_result_column = 0;
            $this->windowState->currentTable = DataExplorerTableData::structureFromTable(
                $this->windowState->table_selection_tables[$this->windowState->table_selection_index],
                $this->connection
            );
            $this->windowState->is_loading_result = false;
        } else {
            $this->loadTable($this->windowState->table_selection_index);
        }
    }

    private function loadTable(int|string $index, ?int $page = null, ?string $order_by_column = null): void
    {
        $index = is_string($index) ? array_search($index, $this->windowState->table_selection_tables) : $index;

        if ($index === -1 || $index === false) {
            $this->loadTableOverview();

            return;
        }

        if (! isset($this->windowState->table_selection_tables[$index])) {
            return;
        }

        $this->windowState->is_loading_result = true;
        $this->windowState->table_selection_index = $index;
        $this->windowState->selected_result_row = -1;

        if ($this->windowState->currentTable?->name !== $this->windowState->table_selection_tables[$index]) {
            $this->windowState->selected_result_column = 0;
        } elseif ($this->windowState->currentTable instanceof DataExplorerTableData) {
            $order_by_column ??= $this->windowState->currentTable->order_by;
        }

        $this->windowState->currentTable = DataExplorerTableData::fromTable(
            $this->windowState->table_selection_tables[$index],
            $this->connection,
            $this->page_height - 2,
            max(1, $page ?? 1),
            $order_by_column
        );
        $this->windowState->is_loading_result = false;
    }

    private function orderByColumn(): void
    {
        if (! $this->windowState->currentTable instanceof DataExplorerTableData
            || $this->windowState->currentTable->page === null
            || ! isset($this->windowState->currentTable->columns[$this->windowState->selected_result_column])
        ) {
            return;
        }

        $order_by_column = $this->windowState->currentTable->columns[$this->windowState->selected_result_column];

        $this->loadTable(
            $this->windowState->table_selection_index,
            page: null,
            order_by_column: $this->windowState->currentTable->order_by !== $order_by_column
                ? $order_by_column
                : '-'.$order_by_column,
        );
    }

    private function pagination(string $direction): void
    {
        if (! $this->windowState->currentTable instanceof DataExplorerTableData
            || $this->windowState->currentTable->page === null
        ) {
            return;
        }

        switch ($direction) {
            case 'next':
                if ($this->windowState->currentTable->page < $this->windowState->currentTable->last_page) {
                    $this->loadTable(
                        $this->windowState->table_selection_index,
                        $this->windowState->currentTable->page + 1
                    );
                }
                break;

            case 'previous':
                $this->loadTable(
                    $this->windowState->table_selection_index,
                    max(0, $this->windowState->currentTable->page - 1)
                );
                break;

            case 'first':
                $this->loadTable(
                    $this->windowState->table_selection_index,
                    1
                );
                break;

            case 'last':
                $this->loadTable(
                    $this->windowState->table_selection_index,
                    $this->windowState->currentTable->last_page
                );
                break;
        }
    }
}
