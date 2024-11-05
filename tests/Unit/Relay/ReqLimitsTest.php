<?php

use nostriphant\Transpher\Relay\Incoming\Req\Limits;
use nostriphant\Transpher\Relay\Incoming\Constraint\Result;

it('has a maximum number of subscriptions per connected client. Defaults to 10. Disabled when set to zero.', function () {
    $subscriptions = \Pest\subscriptions();

    $limits = new Limits(max_per_client: 1);

    $subscription = nostriphant\Transpher\Relay\Condition::makeFiltersFromPrototypes(['ids' => ['a']]);

    $limit = $limits($subscriptions);
    expect($limit->result)->toBe(Result::ACCEPTED, $limit->reason ?? '');

    $subscriptions('sub-id', $subscription);

    $limit = $limits($subscriptions);
    expect($limit->result)->toBe(Result::REJECTED, $limit->reason ?? 'max number of client subscriptions (1) reached');
});
