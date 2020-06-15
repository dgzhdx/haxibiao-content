<?php

namespace haxibiao\content\Traits;

use haxibiao\content\Post;
use App\Exceptions\GQLException;

trait PostResolvers
{
    public function resolveRecommendPosts($root, $args, $context, $info)
    {
        app_track_user_event("获取学习视频");
        return Post::getRecommendPosts();
    }

    public function resolvePosts($root, $args, $context, $info)
    {
        app_track_user_event("个人主页视频动态");
        return Post::posts($args['user_id']);
    }

    /**
     * 动态广场
     */
    public function resolvePublicPosts($root, $args, $context, $info)
    {
        app_track_user_event("访问动态广场");
        return Post::PublicPosts($args['user_id'] ?? null);
    }

    /**
     * 分享视频
     */
    public function getShareLink($rootValue, array $args, $context, $resolveInfo)
    {
        app_track_user('分享视频');
        return Post::shareLink($args['id']);
    }
}
