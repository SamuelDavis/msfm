<?php

namespace PDFParser;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use LogicException;

class PDFParser extends DOMDocument
{
    private $styles;
    private $xPath;

    public function __construct($version = '', $encoding = '')
    {
        parent::__construct($version, $encoding);
        libxml_use_internal_errors(true);
    }

    public static function findReplacements(string $rawText, array $symbols, int $before = 2, int $after = null): array
    {
        $after = str_repeat("[^.!\s\"]* *", $after ?? $before);
        $before = str_repeat("[^.!\s\"]* *", $before);
        $replacements = [];
        foreach ($symbols as $symbol => $replacement) {
            $pattern = "/{$before}{$symbol}{$after}/";
            preg_match_all($pattern, $rawText, $matches);
            $matches = reset($matches);
            $replacements = array_reduce($matches, function (array $acc, string $match) use ($symbol, $replacement) {
                $key = preg_replace("/ ?{$symbol} ?/", ' ', $match);
                $value = str_replace($symbol, $replacement, $match);
                if (array_key_exists($key, $acc) && $value !== $acc[$key]) {
                    throw new LogicException("Duplicate replacement key [{$key}] with value [{$value}]");
                }
                return [$key => $value] + $acc;
            }, $replacements);
        }
        return $replacements;
    }

    /**
     * @param string $dir
     * @return Block[]
     */
    public function getBlocks(string $dir): array
    {
        $files = scandir($dir);
        usort($files, function (string $a, string $b) {
            return intval(preg_replace('/[^0-9]/', '', $a))
                - intval(preg_replace('/[^0-9]/', '', $b));
        });
        return array_reduce($files, function (array $acc, string $file) use ($dir, &$line): array {
            if (!preg_match('/page[0-9]+\.html/', $file)) {
                return $acc;
            }
            $page = intval(preg_replace('/[^0-9]/', '', $file));
            $filepath = "{$dir}/{$file}";
            $this->loadHTMLFile($filepath);
            $divs = iterator_to_array($this->getElementsByTagName('div'));
            return array_merge($acc, array_map(function (DOMElement $div) use ($page, &$line): Block {
                $styles = $this->parseStylesFromElement($div);
                $text = $div->textContent;
                return new Block($page, $text, $styles);
            }, $divs));
        }, []);
    }

    public function loadHTMLFile($filename, $options = 0)
    {
        parent::loadHTMLFile($filename, $options);
        $styles = iterator_to_array($this->getElementsByTagName('style'));
        $this->styles = array_reduce($styles, function (array $acc, DOMElement $style): array {
            $lines = explode("\n", trim($style->textContent));
            return array_reduce($lines, function (array $acc, string $line): array {
                preg_match('/([^{]+) { ([^}]+); }/', $line, $matches);
                [$selector, $styles] = array_slice($matches, 1);
                return [$selector => $this->parseStylesFromText($styles)] + $acc;
            }, $acc);
        }, []);
        $this->xPath = new DOMXPath($this);
    }

    private function parseStylesFromText(string $text): array
    {
        $styles = explode(';', $text);
        return array_reduce($styles, function (array $acc, string $style): array {
            if (!$style) {
                return $acc;
            }
            [$name, $value] = explode(':', $style);
            return [trim($name) => trim($value)] + $acc;
        }, []);
    }

    private function parseStylesFromElement(DOMElement $element): array
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
