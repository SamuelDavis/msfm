<?php

namespace PDFParser;

/**
 * @property int $left
 * @property string fontWeight
 */
class Block
{
    public static $catch = 'fitry';
    public static $debug = true;
    private static $continuedByCallback = null;
    private static $replacements = [];

    public $page;
    public $text;
    public $styles;

    public function __construct(int $page, string $text, array $styles)
    {
        $this->page = $page;
        $this->text = $this->cleanText($text);
        $this->styles = $styles;
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/-\s\w/', '', $text);
        $res = str_replace(array_keys(static::$replacements), array_values(static::$replacements), $text);
        if (static::$catch && stristr($res, static::$catch)) {
            if (static::$debug) {
                foreach (static::$replacements as $key => $value) {
                    if (stristr(str_replace($key, $value, $text), static::$catch)) {
                        dump($key);
                    }
                }
            }
            dump($text, $res, static::$replacements);
        }
        return $res;
    }

    public static function setContinuedByCallback(callable $callback): void
    {
        static::$continuedByCallback = $callback;
    }

    public static function setReplacements(array $replacements): void
    {
        static::$replacements = $replacements;
    }

    public function __get($name)
    {
        $name = strtolower(preg_replace('/([A-Z])/', '-$1', $name));
        $style = $this->styles[$name] ?? null;
        return preg_match('/^[0-9]+px/', $style) ? intval($style) : $style;
    }

    public function merge(Block $block): Block
    {
        $text = substr($this->text, -1) === '-'
            ? "{$this->text}{$block->text}"
            : "{$this->text} {$block->text}";
        $this->text = $this->cleanText($text);
        $this->styles = array_merge($this->styles, $block->styles);
        return $this;
    }

    public function __toString()
    {
        return $this->text;
    }

    public function continuedBy(Block $next): bool
    {
        return static::$continuedByCallback ? call_user_func(static::$continuedByCallback, $this, $next) : false;
    }
}