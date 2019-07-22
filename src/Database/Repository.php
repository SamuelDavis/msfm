<?php

namespace Books\Database;

use Books\Models\Model;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;

class Repository
{
    private $model;
    private $collection;

    /**
     * Repository constructor.
     * @param string|Model $model
     * @throws GoogleException
     */
    public function __construct(string $model)
    {
        $this->model = $model;
        $this->collection = (new FirestoreClient)->collection($model::COLLECTION);
    }

    public function persist(Model $model): Model
    {
        if (!$model->id) {
            $attributes = $model->toArray();
            unset($attributes['text']);
            $model->id = $this->first($attributes)->id ?? null;
        }
        if ($model->id) {
            $document = $this->collection->document($model->id);
            $data = [];
            foreach ($model->toArray() as $path => $value) {
                $data[] = compact('path', 'value');
            }
            $update = $document->update($data);
            /** @var Timestamp $updateTime */
            $model->updatedAt = $update['updateTime'];
        } else {
            $document = $this->collection->newDocument();
            $model->id = $document->id();
            $create = $document->set($model->toArray());
            $model->updatedAt = $create['updateTime'];
        }
        return $model;
    }

    public function first(array $query = [], array $extra = []): ?Model
    {
        return $this->lookup($query, ['limit' => 1] + $extra)[0] ?? null;
    }

    /**
     * @param array $attributes
     * @param array $extra
     * @return Model[]
     */
    public function lookup(array $attributes = [], array $extra = []): array
    {
        $query = $this->collection;
        foreach ($attributes as $name => $value) {
            $query = $query->where($name, '=', $value);
        }
        return array_map(function (DocumentSnapshot $document) {
            return new $this->model($document->data() + ['path' => $document->path()]);
        }, $query->documents($extra)->rows());
    }
}