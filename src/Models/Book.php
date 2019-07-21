<?php

namespace Books\Models;

/**
 * @property string $author
 * @property string $title
 * @property int $year
 */
class Book extends Model
{
    const COLLECTION = 'books';
    const ATTR_AUTHOR = 'author';
    const ATTR_TITLE = 'title';
    const ATTR_YEAR = 'year';
}