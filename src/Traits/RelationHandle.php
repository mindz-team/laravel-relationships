<?php

namespace Mindz\LaravelRelationships\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait RelationHandle
{
    private function handleBelongsTo(Model $model, $relation)
    {
        $data = $model->relationships[$relation] ?? null;
        $relation = Str::camel($relation);
        $keyField = $model->$relation()->getForeignKeyName();
        $model->forceFill([$keyField => is_array($data) && isset($data['id']) ? $data['id'] : $data]);
    }

    private function handleHasOne(Model $model, $relation)
    {
        $data = $model->relationships[$relation] ?? null;

        $relation = Str::camel($relation);

        if (!$model->$relation && is_null($data)) {
            return;
        }

        if ($model->$relation && is_null($data)) {
            $model->$relation->delete();
            return;
        }

        if (!$model->$relation) {
            $model->$relation()->create($data);
            return;
        }

        $model->$relation->update($data);
    }

    private function handleBelongsToMany(Model $model, $relation): void
    {
        $data = $model->relationships[$relation] ?? null;

        if (is_null($data)) {
            return;
        }

        $relation = Str::camel($relation);

        if ($this->actionIs($data, 'attach')) {
            $this->attach($data, $model, $relation);
            return;
        }

        if ($this->actionIs($data, 'detach')) {
            $this->detach($data, $model, $relation);
            return;
        }

        if ($this->actionIs($data, 'update')) {
            $this->syncWithoutDetaching($data, $model, $relation);
            return;
        }

        $this->sync($data, $model, $relation);
    }

    public function actionIs($data, string $action): bool
    {
        if (!isset($data[$action]) || !is_array($data[$action])) {
            return false;
        }

        return true;
    }

    public function attach($data, $model, $relation): void
    {
        $objectsCollection = collect($data['attach']);

        $idsAlreadyAttached = $model->$relation()
            ->whereIn($model->$relation()->getQualifiedRelatedPivotKeyName(), $objectsCollection->pluck('id'))
            ->pluck($model->$relation()->getTable() . '.' . $model->$relation()->getRelatedPivotKeyName());

        if ($idsAlreadyAttached->isNotEmpty()) {
            $objectsCollection = $objectsCollection->reject(fn($item) => in_array($item['id'], $idsAlreadyAttached->toArray()));
        }

        $model->$relation()->attach($this->moveIdAsKey($objectsCollection));
    }

    public function moveIdAsKey($objectsCollection)
    {
        return $objectsCollection->keyBy('id')->map(function ($item) {
            return Arr::except($item, ['id']);
        });
    }

    public function detach($data, $model, $relation)
    {
        $objectsCollection = collect($data['detach']);

        return $model->$relation()->detach($objectsCollection->pluck('id')->toArray());
    }

    public function sync($data, $model, $relation)
    {
        $objectsCollection = collect($data);

        if (Schema::hasColumn($model->$relation()->getTable(), 'position')) {
            $objectsCollection->transform(fn($data, $index) => array_merge($data, ['position' => $index]));
        }

        $model->$relation()->sync($this->moveIdAsKey($objectsCollection));
    }

    public function syncWithoutDetaching($data, $model, $relation)
    {
        $objectsCollection = collect($data['update']);

        $model->$relation()->syncWithoutDetaching($this->moveIdAsKey($objectsCollection));
    }

    private function handleHasMany(Model $model, $relation): void
    {
        $data = $model->relationships[$relation] ?? null;

        if (is_null($data)) {
            return;
        }

        $relation = Str::camel($relation);

        if (isset($data['delete']) && is_array($data['delete'])) {
            $model->$relation()
                ->whereIn('id', collect($data['delete'])->whereNotNull('id')->pluck('id'))
                ->delete();

            return;
        }

        if (isset($data['detach']) && is_array($data['detach'])) {
            $model->$relation()
                ->whereIn('id', collect($data['detach'])->where('id', '!=', null)->pluck('id'))
                ->update([$model->getForeignKey() => null]);
            return;
        }

        if (isset($data['attach']) && is_array($data['attach'])) {
            $relatedModel = $model->$relation()->getRelated();
            $relatedObjects = $relatedModel->find(collect($data['attach'])->pluck('id'));

            $model->$relation()->saveMany($relatedObjects);
            return;
        }

        if (isset($data['add']) && is_array($data['add'])) {
            $objectsCollectionToCreate = collect($data['add'])->where('id', null);
            $objectsCollectionToAttach = collect($data['add'])->where('id', '!=', null);

            if ($objectsCollectionToCreate->isNotEmpty()) {
                $model->$relation()->createMany($objectsCollectionToCreate->toArray());
            }

            if ($objectsCollectionToAttach->isNotEmpty()) {
                $relatedModel = $model->$relation()->getRelated();
                $relatedObjects = $relatedModel->find($objectsCollectionToAttach->pluck('id'));
                $model->$relation()->saveMany($relatedObjects);
            }

            return;
        }

        $objectsCollection = collect($data);

        $model->$relation()
            ->whereNotIn('id', $objectsCollection->where('id', '!=', null)->pluck('id'))
            ->delete();

        if (Schema::hasColumn($model->$relation()->getRelated()->getTable(), 'position')) {
            $objectsCollection->transform(fn($data, $index) => array_merge($data, ['position' => $index]));
        }

        $objectsCollection->where('id', '!=', null)
            ->each(fn($item) => $model->$relation()->find($item['id'])->update($item));

        $model->$relation()->createMany($objectsCollection->where('id', '=', null)->toArray());
    }

    private function handleMorphToMany(Model $model, $relation)
    {
        return $this->handleBelongsToMany($model, $relation);
    }
}
