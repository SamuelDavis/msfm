<pre>
<?php

use Books\Database\Repository;
use Books\Models\Block;
use Books\Models\Book;
use Books\Parsers\MSFM;

require_once __DIR__ . '/vendor/autoload.php';

$bookRepo = new Repository(Book::class);
$blockRepo = new Repository(Block::class);

$msfm = $bookRepo->first();

$blocks = call_user_func(new MSFM, __DIR__ . '/book_html', $msfm);

foreach ($blocks as $block) {
    $blockRepo->persist($block);
}
