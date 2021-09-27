<?php

namespace Mindz\LaravelRelationships\Traits;

use Illuminate\Support\Str;
use Mindz\LaravelRelationships\Observers\RelationshipObserver;

trait Relationship
{
    public $relationships = [];

    public static function bootRelationship()
    {
        static::observe(RelationshipObserver::class);
    }

    public function initializeRelationship()
    {
        $this->fillable(array_merge($this->fillable, $this->getRelationships()));
    }

    public function getRelationships(): array
    {
        return $this->snakeArray($this->relations());
    }

    public function snakeArray($array, $onlyKeys = false): array
    {
        if (!$onlyKeys) {
            return collect($array)->transform(fn($field) => is_null($field) ? null : Str::snake($field))->toArray();
        }

        return array_combine(
            collect($array)->map(fn($field, $key) => Str::snake($key))->toArray(),
            array_values($array)
        );
    }
}
