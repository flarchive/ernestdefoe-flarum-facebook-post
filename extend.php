<?php

use Flarum\Extend;
use Ernestdefoe\FacebookPost\Listener\PostDiscussionToFacebook;
use Flarum\Post\Event\Posted;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Event())
        ->listen(Posted::class, PostDiscussionToFacebook::class),

    (new Extend\Settings())
        ->default('ernestdefoe-facebook-post.enabled', false)
        ->default('ernestdefoe-facebook-post.destination_type', 'page')
        ->default('ernestdefoe-facebook-post.allowed_tags', '[]')
        // Graph API version is operator-tunable so the integration survives
        // Meta's ~2-year deprecation cycle without a code release. The
        // default tracks whatever version was current when this build
        // shipped; admins can bump it the moment Meta announces an EOL
        // window for their installed version.
        ->default('ernestdefoe-facebook-post.graph_api_version', 'v19.0'),
];
