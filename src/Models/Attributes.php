<?php

namespace Books\Models;

use stdClass;

class Attributes
{
    private $orig;

    public function __construct()
    {
        $this->orig = new stdClass;
    }

    public function __set($name, $value)
    {
        $this->orig->{$name} = $value;
        $this->{$name} = $value;
    }
}