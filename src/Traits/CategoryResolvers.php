<?php

namespace Haxibiao\Content\Traits;

use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Content\Category;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait CategoryResolvers
{
    // resolvers
    public function resolveAdmins($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $category = self::findOrFail($args['category_id']);
        return $category->admins();
    }

    public function resolveAuthors($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $category = self::findOrFail($args['category_id']);
        return $category->authors();
    }

    public function resolveCategories($root, array $args, $context)
    {
        $filter = $args['filter'] ?? 'hot';
        //TODO 紧急兼容其它站点老数据问题
        //$qb     = \App\Category::whereIn('type', ['video','article']); //视频时代，避开图文老分类
        $qb = Category::whereStatus(1); //需上架

        //确保是近1个月内更新过的专题（旧的老分类适合图文时代，可能很久没人更新内容进入了）
        // $qb = $qb->where('updated_at', '>', now()->addMonth(-1));

        //热门话题
        if ($filter == 'hot') {
            $qb = $qb->orderBy('is_official', 'desc');
        } else {
            //最新话题
            $qb = $qb->orderBy('id', 'desc');
        }

        return $qb;
    }

}
