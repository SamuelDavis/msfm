<?php

namespace Books\Parsers;

use Books\Models\Block;

class MSFM extends BlockParser
{
    public function __construct($version = '', $encoding = '')
    {
        parent::__construct($version, $encoding);
        Block::setContinuesCallback(function (Block $current, Block $previous): bool {
            return ($previous->left === 85 && $current->left < 85)
                || ($current->left !== 85 && ($previous->left === $current->left))
                || (!preg_match('/[a-z]/', $current->text) && !preg_match('/[a-z]/', $previous->text))
                || (preg_match('/[a-z]$/', $previous->text) && preg_match('/^[a-z]/', $current->text));
        });
        Block::setTypeCallback(function (Block $block): ?string {
            if ($block->left > 85) {
                return 'h5';
            }
            return 'p';
        });
    }
}