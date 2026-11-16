<?php

namespace Ecommerce\Shared\Tests;

use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventConsumer;
use Ecommerce\Shared\Messaging\EventHandler;
use Ecommerce\Shared\Messaging\EventPublisher;
use Ecommerce\Shared\Messaging\Outbox;
use Ecommerce\Shared\Messaging\OutboxRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_survives_a_json_round_trip(): void
    {
        $event = new Event('id-1', 'order.placed', 'orders', '2026-11-16T10:00:00+00:00', ['total' => 100], 'corr-1');

        $this->assertEquals($event, Event::fromJson($event->toJson()));
    }

    #[TestWith(['not json'])]
    #[TestWith(['{"id":"1","type":"a"}'])]
    #[TestWith(['{"id":"1","type":"a","source":"s","occurred_at":"now","payload":"text"}'])]
    public function test_malformed_events_are_rejected(string $json): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event::fromJson($json);
    }

    public function test_outbox_record_follows_the_surrounding_transaction(): void
    {
        Context::add('correlation_id', 'corr-42');

        $event = Outbox::record('product.created', ['id' => 'p1']);

        $this->assertSame('test-service', $event->source);
        $this->assertSame('corr-42', $event->correlationId);
        $this->assertDatabaseHas('outbox_messages', ['id' => $event->id, 'type' => 'product.created', 'published_at' => null]);

        try {
            DB::transaction(function (): void {
                Outbox::record('product.updated', ['id' => 'p1']);

                throw new RuntimeException('business change failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertDatabaseMissing('outbox_messages', ['type' => 'product.updated']);
    }

    public function test_relay_publishes_pending_events_in_order_and_marks_them(): void
    {
        $publisher = new FakePublisher;
        $this->app->instance(EventPublisher::class, $publisher);

        $first = Outbox::record('product.created', ['n' => 1]);
        $second = Outbox::record('product.updated', ['n' => 2]);

        $relay = $this->app->make(OutboxRelay::class);

        $this->assertSame(2, $relay->flush());
        $this->assertSame([$first->id, $second->id], array_column($publisher->published, 'id'));
        $this->assertSame('product.created', $publisher->published[0]['type']);
        $this->assertEquals($first, Event::fromJson($publisher->published[0]['body']));
        $this->assertSame(0, DB::table('outbox_messages')->whereNull('published_at')->count());

        $this->assertSame(0, $relay->flush());
        $this->assertCount(2, $publisher->published);
    }

    public function test_relay_keeps_events_pending_when_the_broker_does_not_confirm(): void
    {
        $publisher = new FakePublisher(failOnConfirm: true);
        $this->app->instance(EventPublisher::class, $publisher);
        Outbox::record('product.created', []);

        try {
            $this->app->make(OutboxRelay::class)->flush();
            $this->fail('Expected the relay to throw.');
        } catch (RuntimeException) {
        }

        $this->assertSame(1, DB::table('outbox_messages')->whereNull('published_at')->count());
    }

    public function test_consumer_runs_matching_handlers_exactly_once(): void
    {
        RecordingHandler::$handled = [];
        $consumer = new EventConsumer($this->app, [
            'order.*' => RecordingHandler::class,
            'product.#' => [RecordingHandler::class],
        ]);
        $event = new Event('0193a1b2-0000-7000-8000-000000000001', 'order.placed', 'orders', 'now', [], 'corr-7');

        $this->assertTrue($consumer->process($event));
        $this->assertFalse($consumer->process($event), 'a redelivered event must be skipped');

        $this->assertSame(['order.placed'], RecordingHandler::$handled);
        $this->assertSame('corr-7', Context::get('correlation_id'));
        $this->assertSame(['order.*', 'product.#'], $consumer->bindings());
    }

    public function test_failed_handler_leaves_the_event_unprocessed_so_it_can_be_retried(): void
    {
        $consumer = new EventConsumer($this->app, ['#' => FailingHandler::class]);
        $event = new Event('0193a1b2-0000-7000-8000-000000000002', 'order.placed', 'orders', 'now', []);

        try {
            $consumer->process($event);
            $this->fail('Expected the handler exception to propagate.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseCount('processed_messages', 0);
        $this->assertDatabaseMissing('outbox_messages', ['type' => 'side.effect']);
    }

    #[TestWith(['order.placed', 'order.placed', true])]
    #[TestWith(['order.*', 'order.placed', true])]
    #[TestWith(['order.*', 'order.item.added', false])]
    #[TestWith(['order.*', 'orders.placed', false])]
    #[TestWith(['order.#', 'order.item.added', true])]
    #[TestWith(['order.#', 'order', true])]
    #[TestWith(['#', 'anything.at.all', true])]
    #[TestWith(['*.created', 'product.created', true])]
    #[TestWith(['order.placed', 'order.placed.twice', false])]
    public function test_topic_patterns_match_like_the_broker(string $pattern, string $type, bool $expected): void
    {
        $this->assertSame($expected, EventConsumer::matches($pattern, $type));
    }
}

class FakePublisher implements EventPublisher
{
    /** @var list<array{type: string, id: string, body: string}> */
    public array $published = [];

    public function __construct(private readonly bool $failOnConfirm = false) {}

    public function publish(string $type, string $messageId, string $body): void
    {
        $this->published[] = ['type' => $type, 'id' => $messageId, 'body' => $body];
    }

    public function confirm(): void
    {
        if ($this->failOnConfirm) {
            throw new RuntimeException('broker unavailable');
        }
    }
}

class RecordingHandler implements EventHandler
{
    /** @var list<string> */
    public static array $handled = [];

    public function handle(Event $event): void
    {
        self::$handled[] = $event->type;
    }
}

class FailingHandler implements EventHandler
{
    public function handle(Event $event): void
    {
        Outbox::record('side.effect', []);

        throw new RuntimeException('handler failed');
    }
}
