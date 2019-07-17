<?php

function dd(...$things): void
{
    dump(...$things);
    exit;
}

function dump(...$things): void
{
    $things = array_map(function ($thing) {
        return is_string($thing)
            ? $thing
            : json_encode($thing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }, $things);
    if ($things) {
        echo '<pre>' . implode('</pre><pre>', $things) . '</pre>';
    }
}
