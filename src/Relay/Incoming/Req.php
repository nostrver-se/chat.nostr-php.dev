<?php

namespace rikmeijer\Transpher\Relay\Incoming;
use rikmeijer\Transpher\Relay\Incoming;
use rikmeijer\Transpher\Relay\Store;
use rikmeijer\Transpher\Relay\Subscriptions;
use rikmeijer\Transpher\Nostr\Message\Factory;
use rikmeijer\Transpher\Relay\Sender;
use rikmeijer\Transpher\Relay\Condition;
use function \Functional\map,
 \Functional\partial_left;

/**
 * Description of Req
 *
 * @author rmeijer
 */
readonly class Req implements Incoming {

    private array $filters;

    public function __construct(private Store $events, private Sender $relay, private string $subscription_id, array ...$filters) {
        $this->filters = array_filter($filters);
    }

    #[\Override]
    static function fromMessage(array $message): callable {
        if (count($message) < 3) {
            throw new \InvalidArgumentException('Invalid message');
        }

        return fn(Store $events, Sender $relay): self => new self($events, $relay, ...array_slice($message, 1));
    }

    #[\Override]
    public function __invoke(): \Generator {
        if (count($this->filters) === 0) {
            yield Factory::closed($this->subscription_id, 'Subscription filters are empty');
        } else {
            $filters = Condition::makeFiltersFromPrototypes(...$this->filters);
            Subscriptions::subscribe($this->relay, $this->subscription_id, $filters);
            $subscribed_events = fn(string $subscriptionId) => map(($this->events)($filters), partial_left([Factory::class, 'requestedEvent'], $subscriptionId));
            yield from $subscribed_events($this->subscription_id);
            yield Factory::eose($this->subscription_id);
        }
    }
}
