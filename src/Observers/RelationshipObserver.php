<?php

namespace Mindz\LaravelRelationships\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mindz\LaravelRelationships\Traits\RelationHandle;

class RelationshipObserver
{
    use RelationHandle;

    public function saving(Model $model)
    {
        $model->relationships = Arr::only($model->snakeArray($model->getAttributes(), true), $model->getRelationships());
        $model->setRawAttributes(array_diff_key($model->getAttributes(), $this->rejectRelationshipsFromAttributes($model)));
        $this->handleRelation($model, 'saving');
    }

    public function saved(Model $model)
    {
        $this->handleRelation($model, 'saved');
        $model->load(array_keys($model->relationships));
    }

    private function rejectRelationshipsFromAttributes(Model $model): array
    {
        return collect($model->getAttributes())
            ->reject(fn($attribute, $key) => !in_array(Str::snake($key), array_keys($model->relationships)))
            ->toArray();
    }

    private function handleRelation(Model $model, $observerEvent)
    {
        $currentlyAvailableTypes = $this->currentlyAvailableRelationTypes($observerEvent);
        collect($model->relationships)->each(function ($data, $relation) use ($model, $currentlyAvailableTypes) {
            $methodName = Str::camel($relation);
            $relationType = class_basename($model->$methodName());
            $method = sprintf('handle%s', $relationType);

            if (!in_array($relationType, $currentlyAvailableTypes)) {
                return;
            }
            $this->$method($model, $relation);
        });
    }

    private function currentlyAvailableRelationTypes($observerEvent)
    {
        $relationToHandle = [
            'saving' => ['BelongsTo'],
            'saved' => ['HasMany', 'BelongsToMany', 'HasOne', 'MorphToMany'],
        ];

        return $relationToHandle[$observerEvent] ?? [];
    }
}
