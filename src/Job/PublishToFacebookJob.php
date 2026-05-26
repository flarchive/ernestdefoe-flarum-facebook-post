<?php

namespace Ernestdefoe\FacebookPost\Job;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

/**
 * Publishes a single discussion to Facebook off the request thread.
 *
 * Dispatched from `Listener\PostDiscussionToFacebook` after the listener
 * has done all the cheap work (tag filter, settings lookup, image
 * extraction, message composition). The job itself only does the
 * outbound HTTP — that's the slow part we want off the user's
 * "submit reply" critical path.
 *
 * On Flarum's default `sync` queue driver the job still runs inline,
 * but the structural separation means switching to `redis`/`database`/
 * `sqs` in `config.php` flips publishing to truly async with zero code
 * changes. See README "Queue driver" section for the operator guide.
 *
 * Security note: NOTHING secret is carried on the job. Laravel
 * serialises every public property of a `ShouldQueue` job into the
 * queue store (the `jobs` table on `database`, the Redis keyspace on
 * `redis`, the SQS message body on `sqs`) — so a Facebook access
 * token in the constructor would land in that store in plaintext, and
 * a read-only DB exposure or a Redis dump would hand an attacker a
 * live posting credential. The token + numeric target ID are read
 * from `SettingsRepositoryInterface` inside `handle()` instead. The
 * constructor carries only the per-discussion data the settings
 * don't know about (`message`, `link`, `imageUrl`) plus a non-secret
 * `'page'|'group'` flag so the job knows which side of the settings
 * branch to read on the queue-worker side.
 */
class PublishToFacebookJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $destinationType,
        public readonly string $message,
        public readonly string $link,
        public readonly ?string $imageUrl,
    ) {
    }

    public function handle(
        SettingsRepositoryInterface $settings,
        Client $http,
        LoggerInterface $logger,
    ): void {
        $type = $this->destinationType === 'group' ? 'group' : 'page';

        if ($type === 'group') {
            $accessToken = (string) $settings->get('ernestdefoe-facebook-post.group_access_token');
            $targetId    = (string) $settings->get('ernestdefoe-facebook-post.group_id');
            $targetLabel = 'Group';
        } else {
            $accessToken = (string) $settings->get('ernestdefoe-facebook-post.page_access_token');
            $targetId    = (string) $settings->get('ernestdefoe-facebook-post.page_id');
            $targetLabel = 'Page';
        }

        if ($accessToken === '' || $targetId === '') {
            $logger->warning("[FacebookPost] Missing Facebook {$targetLabel} access token or ID — job skipped.");
            return;
        }

        $version = trim((string) $settings->get(
            'ernestdefoe-facebook-post.graph_api_version',
            'v19.0'
        ));
        if ($version === '' || ! preg_match('/^v\d+\.\d+$/', $version)) {
            $version = 'v19.0';
        }

        if ($this->imageUrl !== null && $this->imageUrl !== '') {
            $caption = "{$this->message}\n\n🔗 {$this->link}";
            $ok = $this->publishPhoto($http, $logger, $version, $targetId, $accessToken, $targetLabel, $caption);
            if (! $ok) {
                $logger->info('[FacebookPost] Falling back to link post.');
                $this->publishLink($http, $logger, $version, $targetId, $accessToken, $targetLabel, $caption, '');
            }
            return;
        }

        $this->publishLink($http, $logger, $version, $targetId, $accessToken, $targetLabel, $this->message, $this->link);
    }

    /**
     * Uploads the image directly to Facebook so it always displays
     * regardless of domain verification or OG-scraping behaviour.
     * Returns false on any failure so the caller can fall back to a
     * link post.
     */
    private function publishPhoto(
        Client $http,
        LoggerInterface $logger,
        string $version,
        string $targetId,
        string $accessToken,
        string $targetLabel,
        string $caption,
    ): bool {
        $endpoint = "https://graph.facebook.com/{$version}/{$targetId}/photos";

        $payload = [
            'url'          => $this->imageUrl,
            'caption'      => $caption,
            'access_token' => $accessToken,
        ];

        try {
            $response = $http->post($endpoint, [
                'form_params'     => $payload,
                'timeout'         => 15,
                'connect_timeout' => 5,
                'http_errors'     => false,
            ]);
        } catch (GuzzleException $e) {
            $logger->error("[FacebookPost] Photo post transport error: {$e->getMessage()}");
            return false;
        }

        $status  = $response->getStatusCode();
        $body    = (string) $response->getBody();
        $decoded = json_decode($body, true) ?? [];

        if ($status !== 200 || ! empty($decoded['error'])) {
            $err = Arr::get($decoded, 'error.message', $body);
            $logger->error("[FacebookPost] Photo post API error (HTTP {$status}): {$err}");
            return false;
        }

        $postId = Arr::get($decoded, 'post_id', Arr::get($decoded, 'id', 'unknown'));
        $logger->info("[FacebookPost] Successfully posted photo to {$targetLabel}. Post ID: {$postId}");
        return true;
    }

    /**
     * Falls back to a plain link/message post; Facebook scrapes OG
     * tags from the URL to build the preview card.
     */
    private function publishLink(
        Client $http,
        LoggerInterface $logger,
        string $version,
        string $targetId,
        string $accessToken,
        string $targetLabel,
        string $message,
        string $link,
    ): void {
        $endpoint = "https://graph.facebook.com/{$version}/{$targetId}/feed";

        $payload = [
            'message'      => $message,
            'access_token' => $accessToken,
        ];
        if ($link !== '') {
            $payload['link'] = $link;
        }

        try {
            $response = $http->post($endpoint, [
                'form_params'     => $payload,
                'timeout'         => 15,
                'connect_timeout' => 5,
                'http_errors'     => false,
            ]);
        } catch (GuzzleException $e) {
            $logger->error("[FacebookPost] Link post transport error: {$e->getMessage()}");
            return;
        }

        $status  = $response->getStatusCode();
        $body    = (string) $response->getBody();
        $decoded = json_decode($body, true) ?? [];

        if ($status !== 200 || ! empty($decoded['error'])) {
            $err = Arr::get($decoded, 'error.message', $body);
            $logger->error("[FacebookPost] {$targetLabel} API error (HTTP {$status}): {$err}");
            return;
        }

        $postId = Arr::get($decoded, 'id', 'unknown');
        $logger->info("[FacebookPost] Successfully posted link to {$targetLabel}. Post ID: {$postId}");
    }
}
