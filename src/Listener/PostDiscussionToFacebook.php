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

        $message = "📢 {$discussion->title}\n\n{$snippet}\n\n🔗 {$link}";

        $this->publishToFacebook($targetId, $accessToken, $message, $targetLabel);
    }

    private function passesTagFilter(object $discussion): bool
    {
        $json       = $this->settings->get('ernestdefoe-facebook-post.allowed_tags', '[]');
        $allowedIds = json_decode($json, true);

        if (empty($allowedIds)) {
            return true; // no filter configured — post everything
        }

        $allowedIds = array_map('strval', $allowedIds);

        try {
            $tagIds = $discussion->tags->pluck('id')->map(fn($id) => (string) $id)->toArray();
            return !empty(array_intersect($allowedIds, $tagIds));
        } catch (\Throwable) {
            // flarum/tags not installed — allow all
            return true;
        }
    }

    private function publishToFacebook(
        string $targetId,
        string $accessToken,
        string $message,
        string $targetLabel = 'Page'
    ): void {
        $endpoint = "https://graph.facebook.com/v19.0/{$targetId}/feed";

        $payload = [
            'message'      => $message,
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
            $this->logger->error("[FacebookPost] {$targetLabel} API error (HTTP {$httpCode}): {$errMsg}");
        } else {
            $postId = Arr::get($decoded, 'id', 'unknown');
            $this->logger->info("[FacebookPost] Successfully posted to {$targetLabel}. Post ID: {$postId}");
        }
    }
}
