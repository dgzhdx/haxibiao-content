<?php

namespace Haxibiao\Content\Traits;

use Haxibiao\Content\Category;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait Categorizable
{
    private function categorizableModel(): string
    {
        return config('haxibiao-content.models.category');
    }

    public function category()
    {
        return $this->belongsTo($this->categorizableModel(), 'category_id');
    }

    public function allCategories()
    {
        return $this->morphToMany($this->categorizableModel(), 'categorized')
            ->withPivot(['id', 'submit'])
            ->withTimestamps();
    }

    public function hasCategories()
    {
        return $this->morphToMany($this->categorizableModel(), 'categorized');
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany($this->categorizableModel(), 'categorized')
            ->withPivot(['id', 'submit'])
            ->withTimestamps();
    }

    public function categorize($categories)
    {
        $this->categories()->sync($categories, false);

        return $this;
    }

    public function recategorize($categories = [])
    {
        $this->categories()->sync($categories);

        return $this;
    }

    public function uncategorize($categories)
    {
        $this->categories()->detach($categories);

        return $this;
    }

    public function hasCategory($categories)
    {
        if (is_string($categories)) {
            return $this->categories->contains('name', $categories);
        }

        if ($categories instanceof Category) {
            return $this->categories->contains('id', $categories->id);
        }

        if (is_array($categories)) {
            foreach ($categories as $category) {
                if ($this->hasCategory($category)) {
                    return true;
                }
            }

            return false;
        }

        return $categories->intersect($this->categories)->isNotEmpty();
    }
}
