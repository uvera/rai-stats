<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * Dotted label with a Filament tooltip. The visible text stays short;
 * the hint is the definition of a figure that is not obvious from its name.
 */
final class HoverHint
{
    public static function text(string $label, string $hint): HtmlString
    {
        return new HtmlString(
            '<span style="cursor: help; border-bottom: 1px dotted currentColor" x-tooltip="{ content: '.Js::from($hint).', theme: $store.theme }">'
            .e($label)
            .'</span>'
        );
    }
}
