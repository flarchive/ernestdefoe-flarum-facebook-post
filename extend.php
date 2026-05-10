<?php

use Flarum\Extend;
use Ernestdefoe\FacebookPost\Listener\PostDiscussionToFacebook;
use Flarum\Post\Event\Posted;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    (new Extend\Event())
        ->listen(Posted::class, PostDiscussionToFacebook::class),

    (new Extend\Settings())
        ->default('ernestdefoe-facebook-post.enabled', false),
];
