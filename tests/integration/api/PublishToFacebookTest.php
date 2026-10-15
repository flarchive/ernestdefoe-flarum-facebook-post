<?php

namespace Ernestdefoe\FacebookPost\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;

/**
 * A member starts a discussion through the API, and what would have been
 * sent to the Graph API is recorded instead of sent.
 */
class PublishToFacebookTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'flarum-markdown', 'ernestdefoe-facebook-post');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Tag::class => [
                ['id' => 1, 'name' => 'News', 'slug' => 'news', 'position' => 0],
                ['id' => 2, 'name' => 'Chat', 'slug' => 'chat', 'position' => 1],
                ['id' => 3, 'name' => 'Staff', 'slug' => 'staff', 'position' => 2, 'is_restricted' => true],
            ],
            'group_permission' => [['permission' => 'tag3.startDiscussion', 'group_id' => 3], ['permission' => 'tag3.viewForum', 'group_id' => 3]],
            Discussion::class => [
                ['id' => 1, 'title' => 'Old', 'created_at' => Carbon::now()->subDay(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now()->subDay(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Old</p></t>'],
            ],
            'discussion_tag' => [['discussion_id' => 1, 'tag_id' => 1]],
        ]);

        // flarum/tags' own defaults, set here: its migration writes them in the
        // first test on a fresh database, after settings were already read.
        $this->setting('flarum-tags.min_primary_tags', '1');
        $this->setting('flarum-tags.max_primary_tags', '1');
        $this->setting('flarum-tags.min_secondary_tags', '0');
        $this->setting('flarum-tags.max_secondary_tags', '3');

        $this->setting('ernestdefoe-facebook-post.enabled', '1');
        $this->setting('ernestdefoe-facebook-post.page_access_token', 'page-token');
        $this->setting('ernestdefoe-facebook-post.page_id', '12345');
    }

    private function record(): void
    {
        $stack = HandlerStack::create(new MockHandler(array_fill(0, 4, new Response(200, [], '{"id":"1_2"}'))));
        $stack->push(Middleware::history($this->sent));
        $this->app()->getContainer()->instance(Client::class, new Client(['handler' => $stack]));
    }

    private function start(int $tag, string $content = 'Hello **world**'): void
    {
        $this->record();

        $response = $this->send($this->request('POST', '/api/discussions', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'discussions', 'attributes' => ['title' => 'Big news', 'content' => $content],
                'relationships' => ['tags' => ['data' => [['type' => 'tags', 'id' => (string) $tag]]]]]],
        ]));

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
    }

    /** @return array<string, string> the form fields of the one request sent */
    private function onlyPost(): array
    {
        $this->assertCount(1, $this->sent, 'One Graph API call');
        parse_str((string) $this->sent[0]['request']->getBody(), $fields);

        return $fields;
    }

    #[Test]
    public function a_new_public_discussion_is_posted_to_the_page()
    {
        $this->start(1);

        $this->assertSame('https://graph.facebook.com/v19.0/12345/feed', (string) $this->sent[0]['request']->getUri());
        $fields = $this->onlyPost();
        $this->assertSame("📢 Big news\n\nHello world", $fields['message']);
        $this->assertStringContainsString('/d/2-big-news', $fields['link']);
        $this->assertSame('page-token', $fields['access_token']);
    }

    #[Test]
    public function the_snippet_is_plain_text_not_html_entities()
    {
        $this->start(1, 'Fish & chips, "quoted" and <angles>');

        $this->assertSame("📢 Big news\n\nFish & chips, \"quoted\" and <angles>", $this->onlyPost()['message']);
    }

    #[Test]
    public function a_spoiler_stays_hidden_in_the_snippet()
    {
        $this->start(1, 'Ending: >!the butler did it!<');

        $this->assertStringNotContainsString('butler', $this->onlyPost()['message']);
    }

    #[Test]
    public function nothing_is_posted_that_a_guest_could_not_read()
    {
        $this->start(3);

        $this->assertSame([], $this->sent);
    }

    #[Test]
    public function the_tag_allow_list_is_respected()
    {
        $this->setting('ernestdefoe-facebook-post.allowed_tags', json_encode([2]));

        $this->start(1);
        $this->assertSame([], $this->sent, 'News is not on the list');
    }

    #[Test]
    public function a_reply_is_not_posted()
    {
        $this->record();

        $response = $this->send($this->request('POST', '/api/posts', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'posts', 'attributes' => ['content' => 'A reply'],
                'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]]]],
        ]));

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame([], $this->sent);
    }

    #[Test]
    public function nothing_is_posted_while_switched_off()
    {
        $this->setting('ernestdefoe-facebook-post.enabled', '0');

        $this->start(1);

        $this->assertSame([], $this->sent);
    }
}
