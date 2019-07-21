<?php

namespace Books\Parsers;

use Books\Models\Block;
use Books\Models\Book;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class BlockParser extends DOMDocument
{
    private $xPath;
    private $pageStyles;

    public function __invoke(string $dir, Book $book = null)
    {
        $files = scandir($dir);
        usort($files, function (string $a, string $b) {
            [$a, $b] = array_map(function (string $file) {
                return intval(preg_replace('/[^0-9]/', '', $file));
            }, [$a, $b]);
            return $a - $b;
        });
        return array_reduce($files, function (array $acc, string $file) use ($dir, $book) {
            if (!preg_match('/\.html$/', $file)) {
                return $acc;
            }
            $page = intval(preg_replace('/[^0-9]/', '', $file));
            $filepath = "{$dir}/{$file}";
            $this->loadHTMLFile($filepath);
            return array_reduce($this->getBlocks($page), function (array $acc, Block $block) use ($book) {
                $block->bookId = $book->id ?? null;
                /** @var Block $last */
                $last = end($acc);
                $last && $block->continues($last)
                    ? $last->append($block)
                    : array_push($acc, $block);
                return $acc;
            }, $acc);
        }, []);
    }

    public function loadHTMLFile($filename, $options = 0)
    {
        libxml_use_internal_errors(true);
        parent::loadHTMLFile($filename, $options);
        $this->xPath = new DOMXPath($this);
        $styles = iterator_to_array($this->xPath->query('//style'));
        $this->pageStyles = array_reduce($styles, function (array $acc, DOMElement $style): array {
            $lines = explode("\n", trim($style->textContent));
            return array_reduce($lines, function (array $acc, string $line): array {
                preg_match('/([^{]+) { ([^}]+); }/', $line, $matches);
                [$selector, $styles] = array_slice($matches, 1);
                return [$selector => $this->parseStylesFromText($styles)] + $acc;
            }, $acc);
        }, []);
    }

    private function parseStylesFromText(string $text): array
    {
        $styles = explode(';', $text);
        return array_reduce($styles, function (array $acc, string $style): array {
            if ($style) {
                [$name, $value] = explode(':', $style);
                return [trim($name) => trim($value)] + $acc;
            }
            return $acc;
        }, []);
    }

    public function getBlocks(int $page): array
    {
        $index = 0;
        $divs = iterator_to_array($this->getElementsByTagName('div'));
        return array_map(function (DOMElement $div) use (&$index, $page): Block {
            $styles = $this->getStylesForElement($div);
            $text = $div->textContent;
            return new Block([
                Block::ATTR_PAGE => $page,
                Block::ATTR_INDEX => $index++,
                Block::ATTR_TEXT => $text,
                Block::ATTR_STYLES => $styles,
            ]);
        }, $divs);
    }

    private function getStylesForElement(DOMElement $element): array
    {
        $ids = $this->getNestedAttribute($element, 'id');
        $classes = $this->getNestedAttribute($element, 'class');
        $styles = $this->getNestedAttribute($element, 'style');

        $acc = [];
        $acc = array_reduce($styles, function (array $acc, string $styles): array {
            return $this->parseStylesFromText($styles) + $acc;
        }, $acc);
        $acc = array_reduce($classes, function (array $acc, string $classes): array {
            $classes = explode(' ', $classes);
            return array_reduce($classes, function (array $acc, string $class): array {
                return ($this->styles[".{$class}"] ?? []) + $acc;
            }, $acc);
        }, $acc);
        $acc = array_reduce($ids, function (array $acc, string $id): array {
            return ($this->styles["#{$id}"] ?? []) + $acc;
        }, $acc);

        return $acc;
    }

    private function getNestedAttribute(DOMNode $element, string $attr): array
    {
        $acc = $element->hasAttributes() && ($item = $element->attributes->getNamedItem($attr))
            ? [$item->textContent]
            : [];
        $children = $element->hasChildNodes()
            ? iterator_to_array($element->childNodes)
            : [];
        return array_reduce($children, function (array $acc, DOMNode $child) use ($attr) {
            return array_merge($acc, $this->getNestedAttribute($child, $attr));
        }, $acc);
    }
}