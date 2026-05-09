<?php

use Flarum\Extend;
use Ernestdefoe\FacebookPost\Listener\PostDiscussionToFacebook;
use Ernestdefoe\FacebookPost\Api\FacebookPageController;
use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;

return [
    // Register event listener for new posts
    (new Extend\Event())
        ->listen(Posted::class, PostDiscussionToFacebook::class),

    // Admin settings panel
    (new Extend\Admin())
        ->setting(function ($setting) {
            // Settings are registered via JS frontend
        }),

    // Register admin JS & CSS
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    // Expose settings to admin frontend
    (new Extend\Settings())
        ->serializeToForum('ernestdefoe-facebook-post.page_id', 'ernestdefoe-facebook-post.page_id')
        ->serializeToForum('ernestdefoe-facebook-post.enabled', 'ernestdefoe-facebook-post.enabled'),
];
