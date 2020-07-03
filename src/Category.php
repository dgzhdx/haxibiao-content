<?php

namespace Haxibiao\Content;

use Haxibiao\Content\Traits\BaseModel;
use Haxibiao\Content\Traits\CategoryAttrs;
use Haxibiao\Content\Traits\CategoryRepo;
use Haxibiao\Content\Traits\CategoryResolvers;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use CategoryResolvers;
    use CategoryAttrs;
    use CategoryRepo;
    use BaseModel;

    const LOGO_PATH = '/images/category.logo.jpg';

    protected $fillable = [
        'name',
        'name_en',
        'description',
        'user_id',
        'parent_id',
        'type',
        'order',
        'status',
        'request_status',
        'is_official',
        'is_for_app',
        'logo_app',
    ];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function admins()
    {
        return $this->belongsToMany('App\User')->wherePivot('is_admin', 1)->withTimestamps();
    }

    public function authors()
    {
        return $this->belongsToMany('App\User')
            ->withTimestamps()->withPivot('count_approved')
            ->wherePivot('count_approved', '>', 0)
            ->orderBy('pivot_count_approved', 'desc');
    }

    public function users()
    {
        return $this->belongsToMany('App\User');
    }

    public function videoArticles()
    {
        return $this->categorized(\App\Article::class)
            ->where('articles.type', 'video');
    }

    public function containedVideoPosts()
    {
        return $this->categorized(\App\Article::class)
            ->where('articles.type', 'video');
    }

    public function issues()
    {
        return $this->categorized(\App\Issue::class);
    }

    public function articles()
    {
        return $this->categorized(\App\Article::class)
            ->withPivot('submit')
            ->withTimestamps()
            ->orderBy('pivot_updated_at', 'desc')
            ->exclude(['body', 'json']);
    }

    public function videoPosts()
    {
        return $this->articles()->where('type', 'video');
    }

    public function newRequestArticles()
    {
        return $this->articles()
            ->wherePivot('submit', '待审核')
            ->withPivot('updated_at');
    }

    public function requestedInMonthArticles()
    {
        return $this->categorized(\App\Article::class)
            ->wherePivot('created_at', '>', \Carbon\Carbon::now()->addDays(-90))
            ->withPivot('submit', 'created_at')
            ->withTimestamps()
            ->orderBy('pivot_created_at', 'desc');
    }

    public function publishedArticles()
    {
        return $this->articles()
            ->where('articles.status', '>', 0)
            ->wherePivot('submit', '已收录');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function follows()
    {
        return $this->morphMany(\App\Follow::class, 'followed');
    }

    public function publishedWorks()
    {
        return $this->categorized(\App\Article::class)
            ->where('articles.status', '>', 0)
            ->wherePivot('submit', '已收录')
            ->withPivot('submit')
            ->withTimestamps();
    }

    public function subCategory()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id');
    }

    public function hasManyArticles()
    {
        return $this->hasMany('App\Article', 'category_id', 'id');
    }

    public function categorized($related)
    {
        return $this->morphedByMany($related, 'categorized');
    }
}
