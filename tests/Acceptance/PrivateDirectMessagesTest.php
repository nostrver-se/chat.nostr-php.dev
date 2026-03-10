<?php

use nostriphant\NIP01\Key;
use nostriphant\NIP19\Bech32;
use nostriphant\TranspherTests\Listener;
use nostriphant\TranspherTests\Transpher;
use nostriphant\TranspherTests\Factory;

use nostriphant\Client\Client;
use nostriphant\NIP01\Message;


it('starts relay and sends private direct messsage to relay owner', function (string $sender_hex, string $recipient_hex) {
    $sender = Key::fromHex($sender_hex);
    $recipient = Key::fromHex($recipient_hex);
    
    $transpher = new Transpher('8087', $recipient, null);
    
    try {
        $alice = Client::connectToUrl($transpher->ws);
        $bob = Client::connectToUrl($transpher->ws);

        expect($alice)->toBeCallable('Alice is not callable');

        $alice_listener = new Listener('alice-8087', $recipient);
        $alice_listen = $alice(function(callable $send) use ($alice_listener, $recipient, $transpher) {
            $subscription = Factory::subscribe(['#p' => [$recipient(Key::public())]]);

            $subscriptionId = $subscription()[1];
            $send($subscription);

            Listener::expect($alice_listener, ['EVENT', $subscriptionId, 'Hello, I am your agent! The URL of your relay is ' . $transpher->ws]);
            Listener::expect($alice_listener, ['EVENT', $subscriptionId, 'Running with public key npub15fs4wgrm7sllg4m0rqd3tljpf5u9a2g6443pzz4fpatnvc9u24qsnd6036']);
            Listener::expect($alice_listener, ['EOSE', $subscriptionId]);

            $request = $subscription();
            expect($request[2])->toBeArray();
            expect($request[2]['#p'])->toContain($recipient(Key::public()));

            $signed_message = Factory::event($recipient, 1, 'Hello!');
            $send($signed_message);
            Listener::expect($alice_listener, ['OK', $signed_message()[1]['id'], true, ""]);
        });

        expect($alice_listen)->toBeCallable('Alice listen is not callable');

        $alice_listen($alice_listener);

        $bob_message = Factory::event($sender, 1, 'Hello!');

        $bobs_expected_messages = [];
        $bob_listener = new Listener('bob-8087', $sender);

        expect($bob)->toBeCallable('Bob is not callable');

        $bob_listen = $bob(function(callable $send) use ($bob_message, $bob_listener) {
            $send($bob_message);
            Listener::expect($bob_listener, ['OK', $bob_message()[1]['id'], true, '']);

            $send(Message::req('sddf', ["kinds" => [1059], "#p" => ["ca447ffbd98356176bf1a1612676dbf744c2335bb70c1bc9b68b122b20d6eac6"]]));
            Listener::expect($bob_listener, ['EOSE', 'sddf']);
        });

        expect($bob_listener->expected_messages)->toHaveCount(2);
        expect($bob_listen)->toBeCallable('Bob listen is not callable');

        $bob_listen($bob_listener);

        expect($bob_listener->expected_messages)->toHaveCount(0);

        $events = new nostriphant\Stores\Engine\SQLite(new SQLite3($transpher->data_directory . '/transpher.sqlite'), []);

        $notes_alice = iterator_to_array(nostriphant\Stores\Store::query($events, ['authors' => [$recipient(Key::public())], 'kinds' => [1]]));
        expect($notes_alice[0]->kind)->toBe(1);
        expect($notes_alice[0]->content)->toBe('Hello!');

        $notes_bob = iterator_to_array(nostriphant\Stores\Store::query($events, ['ids' => [$bob_message()[1]['id']]]));
        expect($notes_bob)->toHaveLength(1);
        expect($notes_bob[0]->kind)->toBe(1);
        expect($notes_bob[0]->content)->toBe('Hello!');

        $pdms = iterator_to_array(nostriphant\Stores\Store::query($events, ['#p' => [$recipient(Key::public())]]));
        expect($pdms[0]->kind)->toBe(1059);

        expect(file_get_contents(ROOT_DIR . '/logs/relay-6c0de3-output.log'))->not()->toContain('ERROR');
    } catch (\Exception $e) {
        $transpher();
        throw $e;
    }
    
    $transpher();
    
})->with([
    ['a71a415936f2dd70b777e5204c57e0df9a6dffef91b3c78c1aa24e54772e33c3', '6eeb5ad99e47115467d096e07c1c9b8b41768ab53465703f78017204adc5b0cc']
]);