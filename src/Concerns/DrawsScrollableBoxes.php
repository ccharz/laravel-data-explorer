<?php

namespace Ccharz\DataExplorer\Concerns;

use Closure;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

trait DrawsScrollableBoxes
{
    use Colors;
    use InteractsWithStrings;

    protected function fixedWidth(string $text, int $width, string $char = ' '): string
    {
        $text_width = mb_strwidth($this->stripEscapeSequences($text));

        return $text_width <= $width
            ? $text.str_repeat($char, max(0, $width - $text_width))
            : mb_strimwidth($text, 0, $width - 1).'…';
    }

    /**
     * @param  string[]|Closure(int,string,int): string[]  $lines
     * @return string[]
     */
    public function scrollableBox(
        int $width,
        int $height,
        array|Closure $lines,
        int $current_line = 0,
        string $title = '',
        string $footer = '',
        int $fixed_rows = 0,
        string $border_color = 'dim'
    ): array {
        $viewport_width = $width - 5; /* Inner width, without borders */
        $viewport_height = $height - 2; /* Inner height, without borders */

        /** @var string[] $lines */
        $lines = is_callable($lines)
            ? call_user_func($lines, $viewport_width, $border_color, $current_line)
            : $lines;

        $content_height = max(0, count($lines) - $fixed_rows);

        $has_scrollbar = count($lines) > $viewport_height;

        $scrollable_height = max(0, $viewport_height - $fixed_rows);
        $scroll_thumb_height = $has_scrollbar
            ? (int) max(1, floor(($scrollable_height / $content_height) * $scrollable_height))
            : 0;
        $scroll_thumb_position = $has_scrollbar
            ? (int) floor(
                ((max(0, min($current_line, $content_height - $scrollable_height))) / ($content_height - $scrollable_height))
                    * ($scrollable_height - $scroll_thumb_height)
            )
            : 0;

        /* Content starting line */
        $content_offset = max(0, min($current_line, $content_height - $scrollable_height));

        /* Prepare title for border-top */
        $title = $title !== '' ? $this->truncate($title, $viewport_width - 2) : '';
        $title_label = $title != '' ? ' '.$title.' ' : '';
        $title_length = mb_strwidth($this->stripEscapeSequences($title_label));

        /* Prepare footer for border-bottom */
        $footer = $footer !== '' ? $this->truncate($footer, $viewport_width - 2) : '';
        $footer_label = $footer !== '' ? ' '.$footer.' ' : '';
        $footer_length = mb_strwidth($this->stripEscapeSequences($footer_label));

        /* Border-Top */
        $border_top = $this->$border_color(' ┌─'.$title_label.str_repeat('─', max(0, $viewport_width - $title_length)).'─┐');

        /* Border Bottom */
        $border_bottom = $this->$border_color(' └─'.$footer_label.str_repeat('─', max(0, $viewport_width - $footer_length)).'─┘');

        return [
            $border_top,
            ...(
                collect($lines)
                    ->slice(0, $fixed_rows)
                    ->map(fn (string $line): string => $this->{$border_color}(' │ ').$this->pad($line, $viewport_width).$this->{$border_color}(' │'))
                    ->concat(
                        collect($lines)
                            ->slice($fixed_rows + $content_offset, $scrollable_height)
                            ->pad($scrollable_height, str_repeat(' ', $viewport_width))
                            ->values()
                            ->map(fn (string $line, int $index): string => $this->{$border_color}(' │ ').$this->pad($line, $viewport_width).$this->{$border_color}(
                                $has_scrollbar && ($index >= $scroll_thumb_position && $index < $scroll_thumb_position + $scroll_thumb_height)
                                ? ' █'
                                : ' │'
                            ))
                    )
                    ->all()
            ),
            $border_bottom,
        ];
    }
}
