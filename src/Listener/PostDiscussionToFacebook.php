<?php

namespace Ernestdefoe\FacebookPost\Listener;

use Ernestdefoe\FacebookPost\Job\PublishToFacebookJob;
use Flarum\Discussion\Discussion;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
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

    /**
     * Listens to core's Posted and, when flarum/approval is installed, to
     * its PostWasApproved — a discussion held for moderation goes out when
     * a moderator approves it, not when it is written.
     */
    public function handle(object $event): void
    {
        $post = $event->post ?? null;

        // A discussion's opening post is always a comment.
        if (! $post instanceof CommentPost || (int) $post->number !== 1) {
            return;
        }

        if ($event instanceof Posted) {
            // Held for approval (flarum/approval sets is_approved = false
            // before the post is saved). It is published on approval.
            if (array_key_exists('is_approved', $post->getAttributes()) && ! $post->getAttribute('is_approved')) {
                return;
            }

            $this->publish($post);

            return;
        }

        // PostWasApproved. Approval's own listener marks the discussion
        // approved and saves it; if it hasn't run yet, publish once it has.
        $discussion = $post->discussion;
        if ($discussion && ! $discussion->getAttribute('is_approved')) {
            $discussion->afterSave(fn () => $this->publish($post));

            return;
        }

        $this->publish($post);
    }

    private function publish(CommentPost $post): void
    {
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
        $idKey = $destinationType === 'group' ? 'group_id' : 'page_id';
        if (! $this->settings->get("ernestdefoe-facebook-post.{$tokenKey}")
            || ! $this->settings->get("ernestdefoe-facebook-post.{$idKey}")) {
            $this->logger->warning("[FacebookPost] Missing Facebook {$destinationType} access token or ID — post skipped.");

            return;
        }

        $discussion = $post->discussion;

        // The page is public: only share what a logged-out visitor could
        // read on the forum. This rules out restricted tags, private
        // discussions and anything still awaiting approval or hidden.
        if (! $discussion || ! Discussion::query()->whereVisibleTo(new Guest())->whereKey($discussion->id)->exists()) {
            $this->logger->info('[FacebookPost] Skipped — guests cannot see this discussion.');

            return;
        }

        if (! $this->passesTagFilter($discussion)) {
            $this->logger->info('[FacebookPost] Skipped — discussion tag not in allowed list.');

            return;
        }

        $link = $this->url->to('forum')->route('discussion', [
            'id' => $discussion->id.'-'.$discussion->slug,
        ]);

        $contentHtml = $post->formatContent();
        // Decoded: Facebook shows text, so &amp; must arrive as &.
        $contentRaw = html_entity_decode(strip_tags($this->withoutSpoilers($contentHtml)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $snippet = mb_strlen($contentRaw) > 200
            ? mb_substr($contentRaw, 0, 197).'…'
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
        $json = $this->settings->get('ernestdefoe-facebook-post.allowed_tags', '[]');
        $allowedIds = json_decode($json, true);

        if (empty($allowedIds)) {
            return true;
        }

        $allowedIds = array_map('strval', $allowedIds);

        // Fail SAFE: when an allow-list IS configured but the tags can't be
        // read (tags extension uninstalled with the list still set, or a
        // transient query error), default to SKIP rather than allow — a misread
        // filter must never become a flood of every new discussion hitting
        // Facebook. The warning makes the cause findable in flarum.log.
        try {
            $tagIds = $discussion->tags->pluck('id')->map(fn ($id) => (string) $id)->toArray();

            return ! empty(array_intersect($allowedIds, $tagIds));
        } catch (\Throwable $e) {
            $this->logger->warning(
                '[FacebookPost] Tag filter error — skipping this post (an allow-list is '
                ."configured but the discussion's tags couldn't be read): {$e->getMessage()}"
            );

            return false;
        }
    }

    /**
     * Spoilers (inline `||text||` and `>!` blocks) are hidden until clicked
     * on the forum; stripping their tags would print them in the snippet.
     */
    private function withoutSpoilers(string $html): string
    {
        if (! str_contains($html, 'spoiler')) {
            return $html;
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $spoilers = (new \DOMXPath($doc))->query('//*[contains(concat(" ", normalize-space(@class), " "), " spoiler ")]');
        foreach (iterator_to_array($spoilers) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $root = $doc->getElementsByTagName('div')->item(0);
        $out = '';
        foreach ($root->childNodes ?? [] as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
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
