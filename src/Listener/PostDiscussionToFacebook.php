<?php

namespace Ernestdefoe\FacebookPost\Listener;

use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

class PostDiscussionToFacebook
{
    protected SettingsRepositoryInterface $settings;
    protected UrlGenerator $url;
    protected LoggerInterface $logger;

    public function __construct(
        SettingsRepositoryInterface $settings,
        UrlGenerator $url,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->url = $url;
        $this->logger = $logger;
    }

    public function handle(Posted $event): void
    {
        // Only act on the first post of a new discussion (number === 1)
        $post = $event->post;

        if ((int) $post->number !== 1) {
            return;
        }

        // Check extension is enabled
        if (!$this->settings->get('ernestdefoe-facebook-post.enabled')) {
            return;
        }

        $accessToken = $this->settings->get('ernestdefoe-facebook-post.page_access_token');
        $pageId      = $this->settings->get('ernestdefoe-facebook-post.page_id');

        if (!$accessToken || !$pageId) {
            $this->logger->warning('[FacebookPost] Missing Page Access Token or Page ID — post skipped.');
            return;
        }

        $discussion = $post->discussion;
        $title      = $discussion->title;
        $link       = $this->url->to('forum')->route('discussion', ['id' => $discussion->id . '-' . $discussion->slug]);

        // Strip HTML tags from post content for the snippet
        $contentRaw = strip_tags($post->formatContent($post));
        $snippet    = mb_strlen($contentRaw) > 200
            ? mb_substr($contentRaw, 0, 197) . '…'
            : $contentRaw;

        $message = "📢 {$title}\n\n{$snippet}\n\n🔗 {$link}";

        $this->publishToFacebook($pageId, $accessToken, $message, $link);
    }

    /**
     * Send a post to the Facebook Graph API.
     */
    private function publishToFacebook(
        string $pageId,
        string $accessToken,
        string $message,
        string $link
    ): void {
        $endpoint = "https://graph.facebook.com/v19.0/{$pageId}/feed";

        $payload = [
            'message'      => $message,
            'link'         => $link,
            'access_token' => $accessToken,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $this->logger->error("[FacebookPost] cURL error: {$curlErr}");
            return;
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200 || !empty($decoded['error'])) {
            $errMsg = Arr::get($decoded, 'error.message', $response);
            $this->logger->error("[FacebookPost] API error (HTTP {$httpCode}): {$errMsg}");
        } else {
            $postId = Arr::get($decoded, 'id', 'unknown');
            $this->logger->info("[FacebookPost] Successfully posted to Facebook. Post ID: {$postId}");
        }
    }
}
