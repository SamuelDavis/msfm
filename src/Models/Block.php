<?php

namespace Books\Models;

/**
 * @property string $bookId
 * @property int $page
 * @property int $index
 * @property string $type
 * @property string $text
 * @property array $styles
 */
class Block extends Model
{
    const COLLECTION = 'blocks';
    const ATTR_BOOK_ID = 'bookId';
    const ATTR_PAGE = 'page';
    const ATTR_INDEX = 'index';
    const ATTR_TYPE = 'type';
    const ATTR_TEXT = 'text';
    const ATTR_STYLES = 'styles';

    protected static $continuesCallback;
    protected static $typeCallback;

    public static function setContinuesCallback(callable $continuesCallback): void
    {
        self::$continuesCallback = $continuesCallback;
    }

    public static function setTypeCallback(callable $typeCallback): void
    {
        self::$typeCallback = $typeCallback;
    }

    public function __toString()
    {
        $type = $this->type;
        $styles = '';
        foreach ($this->styles as $key => $val) {
            if (in_array($key, ['position'])) {
                continue;
            }
            $styles .= "{$key}:{$val};";
        }
        return <<<HTML
<{$type} style="{$styles}">{$this->text}</{$type}>
HTML;
    }

    public function __get($name)
    {
        $return = parent::__get($name);
        if ($return == null) {
            $name = strtolower(preg_replace('/([A-Z])/', '-$1', $name));
            $return = $this->styles[$name] ?? null;
            if (preg_match('/^[0-9]/', $return)) {
                $return = intval($return);
            }
        }
        return $return;
    }

    public function continues(Block $other): bool
    {
        return is_callable(static::$continuesCallback)
            ? call_user_func(static::$continuesCallback, $this, $other)
            : false;
    }

    /**
     * @param Block $block
     * @return Block|static|$this
     */
    public function append(Block $block): Block
    {
        $this->text .= ' ' . $block->text;
        $this->styles = array_merge($block->styles, $this->styles);
        return $this;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['type' => $this->type];
    }

    protected function getType(): string
    {
        return is_callable(static::$typeCallback)
            ? call_user_func(static::$typeCallback, $this)
            : 'p';
    }
}
