<?php

namespace Ccharz\DataExplorer\Concerns;

use Closure;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Prompt;

trait DrawsPopups
{
    use Colors;

    /**
     * @param  string[]|Closure(int): string[]  $lines
     */
    public function popup(string $prev_frame, int $popup_width, array|Closure $lines): string
    {
        $lines = is_callable($lines) ? call_user_func($lines, $popup_width) : $lines;

        $terminal_width = Prompt::terminal()->cols();
        $terminal_height = Prompt::terminal()->lines();

        $background = $prev_frame !== ''
            ? explode(PHP_EOL, $this->stripEscapeSequences($prev_frame))
            : array_fill(0, $terminal_height, '');

        $output = [];

        $height = count($lines);

        $margin_top = (int) floor(($terminal_height - $height) / 2);
        $margin_left = (int) floor(($terminal_width - $popup_width) / 2);

        foreach ($background as $line_index => $background_line) {
            $line = $background_line;

            if ($line_index >= $margin_top && isset($lines[$line_index - $margin_top])) {
                $output[] = $this->dim(mb_substr($line, 0, $margin_left))
                    .$lines[$line_index - $margin_top]
                    .$this->dim(mb_substr($line, $margin_left + $popup_width, $terminal_width - $margin_left - $popup_width));
            } else {
                $output[] = $this->dim($line);
            }
        }

        return implode(PHP_EOL, $output);
    }
}
