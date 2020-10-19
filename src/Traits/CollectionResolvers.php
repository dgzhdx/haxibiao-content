<?php

namespace Haxibiao\Content\Traits;

use App\Post;
use App\User;
use App\Image;
use App\Visit;
use App\Collection;
use Illuminate\Support\Arr;
use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Base\Exceptions\GQLException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait CollectionResolvers
{
    public function resolveCollections($rootValue, array $args, $context, $resolveInfo)
    {
        return static::where('user_id', data_get($args, 'user_id'))->where('name', 'like', '%' . ($args['keyword'] ?? '') . '%')
            ->orderByDesc('updated_at');
    }

    //分享合集url
    public function getShareLink($rootValue, array $args, $context, $resolveInfo)
    {
        $collection = static::has('posts')->find($args['collection_id']);
        throw_if(is_null($collection), GQLException::class, '该合集不存在哦~,请稍后再试');

        $shareMag = config('haxibiao-content.share_config.share_collection_msg', '#%s/share/post/%d#, #%s#,打开【%s】,直接观看合集视频,玩视频就能赚钱~,');

        if (checkUser() && class_exists("App\\Helpers\\Redis\\RedisSharedCounter", true)) {
            $user = getUser();
            \App\Helpers\Redis\RedisSharedCounter::updateCounter($user->id);
            //触发分享任务
            $user->reviewTasksByClass('Share');
        }

        return sprintf($shareMag, config('app.url'), $collection->id, $collection->description, config('app.name_cn'));
    }

    /**
     * 创建合集
     */
    public function resolveCreateCollection($rootValue, array $args, $context, $resolveInfo)
    {
        $name = data_get($args, 'name');
        $logo = data_get($args, 'logo');
        $collectableType = data_get($args, 'collectable_type');
        $description = data_get($args, 'description', '');
        $collectableIds    = data_get($args, 'collectable_ids');

        if ($logo) {
            $image = Image::saveImage($logo);
            $logo = $image->path;
        } else {
            $logo = config('haxibiao-content.collection_default_logo');
        }

        $collection = static::firstOrCreate([
            'user_id' => getUser()->id,
            'name' => $name,
        ], [
            'description' => $description,
            'logo' => $logo,
            'type' => $collectableType,
            'status' => Collection::STATUS_ONLINE
        ]);
        if ($collectableIds) {
            $collection->collect($collectableIds, $collectableType);
        }
        return $collection;
    }

    public function resolveCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $collection_id = Arr::get($args, 'collection_id');
        app_track_event('合集玩法', '查看合集内视频', $collection_id);
        if (checkUser()) {
            //添加集合浏览记录
            $user = getUser();
            Visit::createVisit($user->id, $collection_id, 'collections');
            $user->reviewTasksByClass('Visit');
        }
        return static::findOrFail($collection_id);
    }

    /**
     * 修改合集
     */
    public function resolveUpdateCollection($rootValue, array $args, $context, $resolveInfo)
    {
        $collection_id = data_get($args, 'collection_id');
        $collection = static::findOrFail($collection_id);

        $logo = Arr::get($args, 'logo');
        if ($logo) {
            $image = Image::saveImage($logo);
            $logo = $image->path;
        } else {
            $logo = $collection->logo;
        }
        $collection->update([
            'logo' => $logo,
            'name' => Arr::get($args, 'name', $collection->name),
            'type' => Arr::get($args, 'type', $collection->type),
            'description' => Arr::get($args, 'description', $collection->description),
        ]);
        return $collection;
    }

    /**
     * 添加资源对象至合集
     */
    public function resolveMoveInCollection($rootValue, array $args, $context, $resolveInfo)
    {
        $collectionId = data_get($args, 'collection_id');
        $collectableIds = data_get($args, 'collectable_ids');
        $collectableType = data_get($args, 'collectable_type');

        $collection = static::find($collectionId);
        if (!$collection) {
            return false;
        }

        $collection->recollect($collectableIds, $collectableType);
        return true;
    }

    /**
     * 移除合集中的资源对象
     */
    public function resolveMoveOutCollection($rootValue, array $args, $context, $resolveInfo)
    {
        $collectionId = data_get($args, 'collection_id');
        $collectableIds = data_get($args, 'collectable_ids');
        $collectableType = data_get($args, 'collectable_type');

        $collection = static::find($collectionId);
        if (!$collection) {
            return false;
        }

        $collection->uncollect($collectableIds, $collectableType);
        return true;
    }

    /**
     * 查询合集下的资源对象列表
     */
    public function resolverPosts($rootValue, $args, $context, $resolveInfo)
    {

        $order       = data_get($args, 'order');
        $currentPage = data_get($args, 'page');
        $perPage     = data_get($args, 'count');

        $qb = $rootValue->posts()->publish();
        $total = $qb->count();

        $postList = $qb->when($order == 'LATEST', function ($q) {
            $q->orderBy('sort_rank');
        })->skip(($currentPage * $perPage) - $perPage)
            ->take($perPage)
            ->get();

        $currentEpisode =  $perPage * ($currentPage - 1) + 1;
        foreach ($postList as $post) {
            $post->current_episode = $currentEpisode;
            $currentEpisode++;
        }

        return new \Illuminate\Pagination\LengthAwarePaginator($postList, $total, $perPage, $currentPage);
    }

    /**
     * 搜索合集
     */
    public function resolveSearchCollections($rootValue, $args, $context, $resolveInfo)
    {
        return static::search(data_get($args, 'query'));
    }

    /**
     * 随机推荐的一组集合
     */
    public function resolveRandomCollections($rootValue, $args, $context, $resolveInfo)
    {
        //过滤掉推荐列表中的集合
        $qb = Collection::whereNull('sort_rank');

        //登录用户
        if (checkUser()) {
            $user = getUser(false);
            //过滤掉自己 和 不喜欢用户的作品
            $notLikIds   = $user->notLikes()->ByType('users')->get()->pluck('not_likable_id')->toArray();
            $notLikIds[] = $user->id;
            $qb          = $qb->whereNotIn('user_id', $notLikIds);

            // //排除浏览过的视频->合集太少，暂时不排除已浏览过的数据
            // $visitVideoIds = Visit::ofType('collections')->ofUserId($user->id)->get()->pluck('visited_id');
            // if (!is_null($visitVideoIds)) {
            //     $qb = $qb->whereNotIn('id', $visitVideoIds);
            // }
        }
        //动态数量大于三的
        $qb = $qb->where('count_posts','>=',3);
        //按照合集创建时间排序
        $qb = $qb->orderby('created_at','desc')
            ->whereBetWeen('created_at', [now()->subDay(30), now()]);

        return $qb;
    }

    /**
     * 推荐集合列表
     */

    public function resolveRecommendCollections($rootValue, $args, $context, $resolveInfo)
    {
        //置顶的合集
        $topCollection = Collection::top()->first();

        $qb = Collection::where('sort_rank', '>=', Collection::RECOMMEND_COLLECTION)
            ->orderby('sort_rank', 'asc');
        $recommendCollectionsA = $qb->take(3)->get();
        $recommendCollectionsB = $qb->take(3)->skip(3)->get();

        //降低rank值，减少出现的概率
        foreach ($recommendCollectionsA as $collectionA) {
            $collectionA->increment('sort_rank');
        }
        foreach ($recommendCollectionsB as $collectionB) {
            $collectionB->increment('sort_rank');
        }
        $result = [];
        //构建返回结果
        $result['topCover']            = Collection::getTopCover();
        $result['topCollection']            = $topCollection;
        $result['recommendCollectionsA']     = $recommendCollectionsA;
        $result['recommendCollectionsB'] = $recommendCollectionsB;

        return $result;
    }
}
