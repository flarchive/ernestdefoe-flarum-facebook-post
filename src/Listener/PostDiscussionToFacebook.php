<?php

namespace Ernestdefoe\FacebookPost\Listener;

use Ernestdefoe\FacebookPost\Job\PublishToFacebookJob;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;

/**
 * Triggered when a new post is created. Decides whether the post is the
 * opening post of a discussion that should be published to Facebook, and
 * if so dispatches `PublishToFacebookJob` to do the actual outbound
 * HTTP off the request thread.
 *
 * Cheap work — tag filter, settings lookup, image extraction, message
 * composition — runs inline so a tag-filtered discussion never costs
 * the queue a wakeup. The expensive work — two potential 15-second
 * timeouts against `graph.facebook.com` — moves to the job so the
 * user's "submit reply" doesn't stall waiting for Facebook.
 */
class PostDiscussionToFacebook
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected UrlGenerator $url,
        protected LoggerInterface $logger,
        protected Dispatcher $bus,
    ) {
    }

    public function handle(Posted $event): void
    {
        $post = $event->post;

        if ((int) $post->number !== 1) {
            return;
        }

        if (! $this->settings->get('ernestdefoe-facebook-post.enabled')) {
            return;
        }

        // The destination-type flag is the ONLY identifying info we
        // pass to the job — the access token + numeric target ID are
        // re-read from settings inside the job's `handle()` so they
        // never get serialised into the queue store. See the security
        // note on PublishToFacebookJob's class docblock.
        $destinationType = $this->settings->get('ernestdefoe-facebook-post.destination_type', 'page') === 'group'
            ? 'group'
            : 'page';

        // Early-out if the destination isn't configured. We check
        // here so a guaranteed-to-fail dispatch never costs the queue
        // a wakeup; the job re-checks the same settings at handle
        // time for the case where they change between dispatch and
        // execution (operator wipes the token while jobs are queued).
        $tokenKey = $destinationType === 'group' ? 'group_access_token' : 'page_access_token';
        $idKey    = $destinationType === 'group' ? 'group_id'           : 'page_id';
        if (! $this->settings->get("ernestdefoe-facebook-post.{$tokenKey}")
            || ! $this->settings->get("ernestdefoe-facebook-post.{$idKey}")) {
            $this->logger->warning("[FacebookPost] Missing Facebook {$destinationType} access token or ID — post skipped.");
            return;
        }

        $discussion = $post->discussion;

        if (! $this->passesTagFilter($discussion)) {
            $this->logger->info('[FacebookPost] Skipped — discussion tag not in allowed list.');
            return;
        }

        $link = $this->url->to('forum')->route('discussion', [
            'id' => $discussion->id . '-' . $discussion->slug,
        ]);

        $contentHtml = $post->formatContent();
        $contentRaw  = strip_tags($contentHtml);
        $snippet     = mb_strlen($contentRaw) > 200
            ? mb_substr($contentRaw, 0, 197) . '…'
            : $contentRaw;

        $message = "📢 {$discussion->title}\n\n{$snippet}";

        // Prefer an image embedded in the post; fall back to the
        // ernestdefoe/og-image setting when present. og-image is an
        // optional integration declared in composer.json's `suggest`
        // block, so settings->get returns null when it isn't installed
        // and the `??` lands cleanly on an empty string — the job
        // then degrades to a link post.
        $imageUrl = $this->extractImageFromHtml($contentHtml)
            ?: (string) ($this->settings->get('ernestdefoe-og-image.default_image') ?? '');

        $this->bus->dispatch(new PublishToFacebookJob(
            destinationType: $destinationType,
            message:         $message,
            link:            $link,
            imageUrl:        $imageUrl !== '' ? $imageUrl : null,
        ));
    }

    private function passesTagFilter(object $discussion): bool
    {
        $json       = $this->settings->get('ernestdefoe-facebook-post.allowed_tags', '[]');
        $allowedIds = json_decode($json, true);

        if (empty($allowedIds)) {
            return true;
        }

        $allowedIds = array_map('strval', $allowedIds);

        // The default-to-allow fallback covers the case where the
        // tags extension was uninstalled with an allow-list still
        // configured (`$discussion->tags` becomes a missing
        // relation), or a transient query error. Either case is
        // operator-actionable, so log it as a warning instead of
        // silently posting every new discussion — an operator who
        // notices a sudden flood of Facebook posts deserves to find
        // the cause in flarum.log without bisecting the code.
        try {
            $tagIds = $discussion->tags->pluck('id')->map(fn ($id) => (string) $id)->toArray();
            return ! empty(array_intersect($allowedIds, $tagIds));
        } catch (\Throwable $e) {
            $this->logger->warning(
                "[FacebookPost] Tag filter error — defaulting to allow: {$e->getMessage()}"
            );
            return true;
        }
    }

    private function extractImageFromHtml(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*/i', $html, $matches)) {
            $src = $matches[1];
            if (! str_starts_with($src, 'data:')) {
                return $src;
            }
        }
        return null;
    }
}
