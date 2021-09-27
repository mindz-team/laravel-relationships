# Laravel Relationships

This is package that helps to manage updating model relationships automatically by performing an update on single model.
When create or update object an observer action is triggered that automatically update, create or sync relations of an
object according to relation type.

# Installation

You can install package via composer. Add repository to your composer.json

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mindz-team/laravel-relationships"
        }
    ],

And run

    composer require mindz-team/laravel-relationships

# Usage

Package handles (for now) only base relations available. Below examples shows how to use it .

## Relation types

Relation types available in package are

- `HasOne`
- `BelongsTo`
- `HasMany`
- `BelongsToMany`

Those (for now) are only relation types that are handled.

## Example model

Let's assume we have an example `Book` model with relations.

    class Book extends Model{
    
        public function author()
        {
            return $this->belongsTo(Author::class);
        }

        public function cover()
        {
            return $this->hasOne(Cover::class);
        }
        
        public function chapters()
        {
            return $this->hasMany(Chapter::class);
        }
        
        public function gender()
        {
            return $this->belongsToMany(Gender::class);
        }
    }

## Select relations

There might not be a necessity to automatically handle all relationships. Therefore, relation that should be handled
automatically should be included in `relations` method in model class like in example below:

    public function relations(): array
    {
        return [
            'author',
            'cover',
            'chapters',
            'gender',
        ];
    }

> Relations not included won't be handled

## BelongsTo

When creating or updating object with `BelongsTo` relation you need simply to append relation name with `id` that refers
to relation object

    Book::create([...$other attributes,'author'=>1]);

or update

    Book::update(['author'=>1]);

or if you desire to remove reference to object from the model you may pass `null` value

    Book::update(['author'=>null]);

## HasOne

To handle this relation type automatically you need to pass an attributes regarding this relation

    Book::create([...$other attributes, "cover" =>['contains_image' => true, 'type' => "soft", 'contains_summary' => true]]);

If relation object is null it will be created. If not it will be updated. If you desire to delete related object you may
pass `null` value

    Book::update(['cover' => null]);

## HasMany

While handling `HasMany` relation you should provide a collection of related objects

    Book::create([...$other attributes, "chapters" =>[
        [
            "name"=> "Chapter I",
            "pages"=> "23",
            "reading_time"=> "15m",
        ],
        [
            "name"=> "Chapter II",
            "pages"=> "14",
            "reading_time"=> "13m",
        ],
    ]);

Note that all above chapters will be created and bounded with `Book` object, but sometimes you need to update relation object. To do this you must provide their `ids`

    $book->update(["chapters" =>[
        [
            "id"=> 1,
            "name"=> "Chapter I",
            "pages"=> "23",
            "reading_time"=> "15m",
        ],
        [
            "id"=> 2,
            "name"=> "Chapter II",
            "pages"=> "14",
            "reading_time"=> "13m",
        ],
    ]);

If related model attributes contain id it will be updated. If it does not - it will be created

> IMPORTANT! In case above all chapters that are not in chapters attributes array will be deleted.

### Adding new object to relation.

If you do not want to pass all present object ids to array (so they would not be deleted) you can wrap collection in `add` array

     $book->update(["chapters" =>[
        'add' => [
            [
                "name"=> "Chapter III",
                "pages"=> "43",
                "reading_time"=> "30m",
            ],
            [
                "name"=> "Chapter IV",
                "pages"=> "8",
                "reading_time"=> "4m",
            ],
        ],
    ]);

Already existing objects associated with book won't be affected.

> Note that if you provide already existing object (without relation to any book - with id) to attributes array it will be attached.

### Removing object from relation.

If you want to remove only one or few objects without passing ids to attributes array (so they would not be deleted) you can wrap collection in `delete` array

     $book->update(["chapters" =>[
        'delete' => [
            [
                "id"=> 3,
            ],
            [
                "id"=> 4,
            ],
        ],
    ]);

Already existing objects associated with book won't be affected.

### Attach already existing object to relation.

If you want to associate existing objects like chapter without assigned relation id (null) you can wrap collection in `attach` array

    $book->update(["chapters" =>[
        'attach' => [
            [
                "id"=> 3,
            ],
            [
                "id"=> 4,
            ],
        ],
    ]);

Existing objects will be associated with book.

### Detach object from relation

If you want to disassociate existing objects (set foreign key to null) you can wrap collection in `detach` array

    $book->update(["chapters" =>[
        'detach' => [
            [
                "id"=> 3,
            ],
            [
                "id"=> 4,
            ],
        ],
    ]);

Ids provided in detach array will be disassociated from book.

> `Remember!` This feature requires setting foreign key of relation to nullable in database<br>
> `Remember!` Association will be performed even if related object is associated with another model.

### Ordering Relation objects

Sometimes you need to sort `hasMany` relations by `position`. In that case all you need to do is to simply pass attributes array with ids in order you want. This can be possible if related objects model contains `position` column in database.

     $book->update(['chapters'=> [
        [
            'id'=>1
        ],
        [
            'id'=>2
        ],
        [
            'id'=>3
        ],
    ]);

Relation objects will be filled with `position` attribute ascending from `0` according to passed array.

> `Remember` Missing ids will be removed. Therefore you have to include all ids

## BelongsToMany

While adding `BelongsToMany` relation objects you should provide a collection of related objects ids

     Book::create([...$other attributes, 'genders'=> [
        [
            'id'=>1
        ],
        [
            'id'=>2
        ],
        [
            'id'=>3
        ],
    ]);

Provided objects will by associated with created model. If you want to pass additional data to store in pivot table simply add it to attributes table

      Book::create([...$other attributes, 'genders'=> [
            [
                'id'=>1,
                'priority'=>3
            ],
            [
                'id'=>2,
                'priority'=>2
            ],
            [
                'id'=>3,
                'priority'=>1
            ]
        ]);

> `Attention` Since Laravel `belongsToMany` class methods `attach` and `sync` use `force` option to fill pivot table remember not to pass any fields that are not in database

### Adding new object to relation

You have also possibility to attach one or few related objects by wrapping collection in `attach` array

     $book->update(['genders'=> [
        "attach" => [
            [
                'id'=>1
            ],
            [
                'id'=>2
            ],
            [
                'id'=>3
            ],
        ]
    ]);

All objects missing in array associated with book won't be affected.

### Removing object from relation.

Same way works for detaching objects.

    $book->update(['genders'=> [
        "detach" => [
            [
                'id'=>1
            ],
            [
                'id'=>2
            ],
            [
                'id'=>3
            ],
        ]
    ]);

All objects missing in array associated with book won't be affected.

### Ordering Relation objects

Similar to `hasMany` sometimes you need to sort `belongsToMany` associated relations by `position`. In that case all you need to do is to simply pass attributes array with ids in order you want. This can be possible when there is `position` column in database on pivot table.

    $book->update(['genders'=> [
        [
            'id'=>1
        ],
        [
            'id'=>2
        ],
        [
            'id'=>3
        ],
    ]);

> `Remember` Missing related objects will be detached

# Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

# Security

If you discover any security related issues, please email r.szymanski@mindz.it instead of using the issue tracker.

# Credits

Author: Roman Szymański [r.szymanski@mindz.it](mailto:r.szymanski@mindz.it)

# License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
