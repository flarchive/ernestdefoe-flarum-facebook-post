<?php

namespace Ernestdefoe\FacebookPost\Listener;

use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

class PostDiscussionToFacebook
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected UrlGenerator $url,
        protected LoggerInterface $logger,
    ) {}

    public function handle(Posted $event): void
    {
        $post = $event->post;

        if ((int) $post->number !== 1) {
            return;
        }

        if (!$this->settings->get('ernestdefoe-facebook-post.enabled')) {
            return;
        }

        $destinationType = $this->settings->get('ernestdefoe-facebook-post.destination_type', 'page');

        if ($destinationType === 'group') {
            $accessToken = $this->settings->get('ernestdefoe-facebook-post.group_access_token');
            $targetId    = $this->settings->get('ernestdefoe-facebook-post.group_id');
            $targetLabel = 'Group';
        } else {
            $accessToken = $this->settings->get('ernestdefoe-facebook-post.page_access_token');
            $targetId    = $this->settings->get('ernestdefoe-facebook-post.page_id');
            $targetLabel = 'Page';
        }

        if (!$accessToken || !$targetId) {
            $this->logger->warning("[FacebookPost] Missing Facebook {$targetLabel} access token or ID — post skipped.");
            return;
        }

        $discussion = $post->discussion;

        if (!$this->passesTagFilter($discussion)) {
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

        // Try to get an image from the post content, then fall back to the
        // og-image default. If we have an image, post as a photo (reliable).
        // If not, post as a link post (relies on Facebook OG scraping).
        $imageUrl = $this->extractImageFromHtml($contentHtml)
            ?: (string) ($this->settings->get('ernestdefoe-og-image.default_image') ?? '');

        if ($imageUrl) {
            $caption = "{$message}\n\n🔗 {$link}";
            $this->publishPhoto($targetId, $accessToken, $imageUrl, $caption, $targetLabel);
        } else {
            $this->publishLink($targetId, $accessToken, $message, $link, $targetLabel);
        }
    }

    private function passesTagFilter(object $discussion): bool
    {
        $json       = $this->settings->get('ernestdefoe-facebook-post.allowed_tags', '[]');
        $allowedIds = json_decode($json, true);

        if (empty($allowedIds)) {
            return true;
        }

        $allowedIds = array_map('strval', $allowedIds);

        try {
            $tagIds = $discussion->tags->pluck('id')->map(fn($id) => (string) $id)->toArray();
            return !empty(array_intersect($allowedIds, $tagIds));
        } catch (\Throwable) {
            return true;
        }
    }

    private function extractImageFromHtml(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*/i', $html, $matches)) {
            $src = $matches[1];
            if (!str_starts_with($src, 'data:')) {
                return $src;
            }
        }
        return null;
    }

    /**
     * Post as a photo — image is uploaded directly to Facebook so it always
     * displays regardless of domain verification or OG scraping behaviour.
     */
    private function publishPhoto(
        string $targetId,
        string $accessToken,
        string $imageUrl,
        string $caption,
        string $targetLabel = 'Page'
    ): void {
        $endpoint = "https://graph.facebook.com/v19.0/{$targetId}/photos";

        $payload = [
            'url'          => $imageUrl,
            'caption'      => $caption,
            'access_token' => $accessToken,
        ];

        $response = $this->post($endpoint, $payload);

        if ($response['error']) {
            $this->logger->error("[FacebookPost] Photo post cURL error: {$response['error']}");
            // Fall back to link post
            $this->logger->info('[FacebookPost] Falling back to link post.');
            $this->publishLink($targetId, $accessToken, $caption, '', $targetLabel);
            return;
        }

        if ($response['http_code'] !== 200 || !empty($response['decoded']['error'])) {
            $errMsg = Arr::get($response['decoded'], 'error.message', $response['body']);
            $this->logger->error("[FacebookPost] Photo post API error (HTTP {$response['http_code']}): {$errMsg}");
            // Fall back to link post
            $this->logger->info('[FacebookPost] Falling back to link post.');
            $this->publishLink($targetId, $accessToken, $caption, '', $targetLabel);
            return;
        }

        $postId = Arr::get($response['decoded'], 'post_id', Arr::get($response['decoded'], 'id', 'unknown'));
        $this->logger->info("[FacebookPost] Successfully posted photo to {$targetLabel}. Post ID: {$postId}");
    }

    /**
     * Post as a link — relies on Facebook scraping OG tags from the URL.
     */
    private function publishLink(
        string $targetId,
        string $accessToken,
        string $message,
        string $link,
        string $targetLabel = 'Page'
    ): void {
        $endpoint = "https://graph.facebook.com/v19.0/{$targetId}/feed";

        $payload = [
            'message'      => $message,
            'access_token' => $accessToken,
        ];

        if ($link !== '') {
            $payload['link'] = $link;
        }

        $response = $this->post($endpoint, $payload);

        if ($response['error']) {
            $this->logger->error("[FacebookPost] Link post cURL error: {$response['error']}");
            return;
        }

        if ($response['http_code'] !== 200 || !empty($response['decoded']['error'])) {
            $errMsg = Arr::get($response['decoded'], 'error.message', $response['body']);
            $this->logger->error("[FacebookPost] {$targetLabel} API error (HTTP {$response['http_code']}): {$errMsg}");
        } else {
            $postId = Arr::get($response['decoded'], 'id', 'unknown');
            $this->logger->info("[FacebookPost] Successfully posted link to {$targetLabel}. Post ID: {$postId}");
        }
    }

    private function post(string $endpoint, array $payload): array
    {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        return [
            'body'      => (string) $body,
            'http_code' => $code,
            'error'     => $curlErr,
            'decoded'   => json_decode((string) $body, true) ?? [],
        ];
    }
}
