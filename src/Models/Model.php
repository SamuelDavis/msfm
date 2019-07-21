<?php

namespace Books\Models;

use Google\Cloud\Core\Timestamp;

/**
 * @property string $id
 * @property string $path
 * @property Timestamp updatedAt
 */
class Model
{
    const COLLECTION = null;
    const ATTR_ID = 'id';
    const ATTR_PATH = 'path';
    const ATTR_UPDATED_AT = 'updatedAt';

    private $attributes;

    public function __construct(iterable $attributes = [])
    {
        $this->attributes = new Attributes;
        foreach ($attributes as $name => $value) {
            $this->{$name} = $value;
        }
    }

    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        $value = $this->attributes->{$name} ?? null;
        return method_exists($this, $method)
            ? $this->{$method}($value)
            : $value;
    }

    public function __set($name, $value)
    {
        $method = 'set' . ucfirst($name);
        return $this->attributes->{$name} = method_exists($this, $method)
            ? $this->{$method}($value)
            : $value;
    }

    public function toArray(): array
    {
        return get_object_vars($this->attributes);
    }
}