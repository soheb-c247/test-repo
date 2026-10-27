<?php

namespace App\Support;

/**
 * Renders free-text reporting notes as safe HTML for reports.
 *
 * Blocks are separated by blank lines. A block whose every line contains
 * "|" becomes a table (first line = header row), e.g. the interpretation
 * tables from the reference workbook; other blocks become paragraphs.
 * All text is escaped.
 */
final class NotesFormatter
{
    public static function toHtml(?string $notes): string
    {
        $notes = trim((string) $notes);
        if ($notes === '') {
            return '';
        }

        $blocks = preg_split('/\R\s*\R/', str_replace("\r\n", "\n", $notes));
        $html = '';

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
            if (! $lines) {
                continue;
            }

            $isTable = count($lines) >= 2 && collect($lines)->every(fn ($l) => str_contains($l, '|'));

            if ($isTable) {
                $html .= '<table class="notes-table">';
                foreach ($lines as $i => $line) {
                    $cells = array_map('trim', explode('|', $line));
                    $tag = $i === 0 ? 'th' : 'td';
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $html .= "<{$tag}>".e($cell)."</{$tag}>";
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';
            } else {
                $html .= '<p>'.implode('<br>', array_map(fn ($l) => e($l), $lines)).'</p>';
            }
        }

        return $html;
    }
}
