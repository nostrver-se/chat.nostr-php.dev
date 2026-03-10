<?php

use nostriphant\NIP01\Key;
use nostriphant\NIP19\Bech32;
use nostriphant\TranspherTests\AcceptanceCase;
use nostriphant\TranspherTests\Transpher;
use nostriphant\TranspherTests\Factory;

use nostriphant\Client\Client;
use nostriphant\NIP01\Message;

describe('only events from whitelisted authors/recipients are stored', function () {
    it('only stores messages from owner and agent, but they are still being delivered', function (string $sender_hex, string $recipient_hex) {
        $sender = Key::fromHex($sender_hex);
        $recipient = Key::fromHex($recipient_hex);
        
        $transpher = new Transpher('8088', $recipient, []);

        try {
            $alice = Client::connectToUrl($transpher->ws);
            $bob = Client::connectToUrl($transpher->ws);
            
            $subscriptionAlice = Factory::subscribe(['#p' => [$recipient(Key::public())]]);

            $bob_message = Factory::event($sender, 1, 'Hello!');
            $subscriptionAliceOnBobsMessage = Factory::subscribe(['ids' => [$bob_message()[1]['id']]]);
            
            $alice_listener = AcceptanceCase::createListener('alice-8088', $recipient);
            $bob_listener = AcceptanceCase::createListener('bob-8088', $sender);
            $bob_listener->expected_messages[] = ['OK', $bob_message()[1]['id'], true, ''];
            
            $bob_listen;
            $alice_listen = $alice(function(callable $send) use ($alice_listener, $subscriptionAlice, $subscriptionAliceOnBobsMessage, $bob, $bob_message, &$bob_listen, $recipient, $transpher) {

                $send($subscriptionAliceOnBobsMessage);
                $alice_listener->expected_messages[] = ['EVENT', $subscriptionAliceOnBobsMessage()[1], 'Hello!'];
                $alice_listener->expected_messages[] = ['EOSE', $subscriptionAliceOnBobsMessage()[1]];
                
                $subscriptionId = $subscriptionAlice()[1];
                $send($subscriptionAlice);

                $alice_listener->expected_messages[] = ['EVENT', $subscriptionId, 'Hello, I am your agent! The URL of your relay is ' . $transpher->ws];
                $alice_listener->expected_messages[] = ['EVENT', $subscriptionId, 'Running with public key npub15fs4wgrm7sllg4m0rqd3tljpf5u9a2g6443pzz4fpatnvc9u24qsnd6036'];
                $alice_listener->expected_messages[] = ['EOSE', $subscriptionId];

                $request = $subscriptionAlice();
                expect($request[2])->toBeArray();
                expect($request[2]['#p'])->toContain($recipient(Key::public()));

                $signed_message = Factory::event($recipient, 1, 'Hello!');
                $send($signed_message);
                
                $alice_listener->expected_messages[] = ['OK', $signed_message()[1]['id'], true, ""];
                
                sleep(1);
                
                $bob_listen = $bob(fn(callable $send) => $send($bob_message));
                
            });
            

            expect($bob_listener->expected_messages)->toHaveCount(1);
            
            $alice_listen($alice_listener);
            $bob_listen($bob_listener);

            $events = new nostriphant\Stores\Engine\SQLite(new SQLite3($transpher->data_directory . '/transpher.sqlite'), []);

            $notes_alice = iterator_to_array(nostriphant\Stores\Store::query($events, ['authors' => [$recipient(Key::public())], 'kinds' => [1]]));
            expect($notes_alice[0]->kind)->toBe(1);
            expect($notes_alice[0]->content)->toBe('Hello!');

            $notes_bob = iterator_to_array(nostriphant\Stores\Store::query($events, ['ids' => [$bob_message()[1]['id']]]));
            expect($notes_bob)->toHaveLength(0);
        } catch (\Exception $e) {
            $transpher();
            throw $e;
        }

        $transpher();
    })->with([
        ['a71a415936f2dd70b777e5204c57e0df9a6dffef91b3c78c1aa24e54772e33c3', '6eeb5ad99e47115467d096e07c1c9b8b41768ab53465703f78017204adc5b0cc']
    ]);
    
    
    it('stores messages from owner, agent and whitelisted', function (string $sender_hex, string $recipient_hex) {
        $sender = Key::fromHex($sender_hex);
        $recipient = Key::fromHex($recipient_hex);
        
        $transpher = new Transpher('8090', $recipient, [(string) Bech32::npub($sender(Key::public()))]);

        try {
            $alice = Client::connectToUrl($transpher->ws);
            $bob = Client::connectToUrl($transpher->ws);
            

            $alice_listener = AcceptanceCase::createListener('alice-8090', $recipient);
            $alice_listen = $alice(function(callable $send) use ($alice_listener, $recipient, $transpher) {
                $subscription = Factory::subscribe(['#p' => [$recipient(Key::public())]]);

                $subscriptionId = $subscription()[1];
                $send($subscription);

                $alice_listener->expected_messages[] = ['EVENT', $subscriptionId, 'Hello, I am your agent! The URL of your relay is ' . $transpher->ws];
                $alice_listener->expected_messages[] = ['EVENT', $subscriptionId, 'Running with public key npub15fs4wgrm7sllg4m0rqd3tljpf5u9a2g6443pzz4fpatnvc9u24qsnd6036'];
                $alice_listener->expected_messages[] = ['EOSE', $subscriptionId];

                $request = $subscription();
                expect($request[2])->toBeArray();
                expect($request[2]['#p'])->toContain($recipient(Key::public()));

                $signed_message = Factory::event($recipient, 1, 'Hello!');
                $send($signed_message);
                $alice_listener->expected_messages[] = ['OK', $signed_message()[1]['id'], true, ""];
            });

            $alice_listen($alice_listener);


            $bob_message = Factory::event($sender, 1, 'Hello!');

            $bobs_expected_messages = [];

            $bob_listen = $bob(function(callable $send) use ($bob_message, &$bobs_expected_messages) {
                $send($bob_message);
                $bobs_expected_messages[] = ['OK', $bob_message()[1]['id'], true, ''];
            });

            expect($bobs_expected_messages)->toHaveCount(1);

            $bob_listen(function (Message $message, callable $stop) use (&$bobs_expected_messages) {
                $expected_message = array_shift($bobs_expected_messages);

                $type = array_shift($expected_message);
                expect($message->type)->toBe($type, 'Message type checks out');
                expect($message->payload)->toBe($expected_message);

                if (count($bobs_expected_messages) === 0) {
                    $stop();
                }
            });


            $events = new nostriphant\Stores\Engine\SQLite(new SQLite3($transpher->data_directory . '/transpher.sqlite'), []);

            $notes_alice = iterator_to_array(nostriphant\Stores\Store::query($events, ['authors' => [$recipient(Key::public())], 'kinds' => [1]]));
            expect($notes_alice[0]->kind)->toBe(1);
            expect($notes_alice[0]->content)->toBe('Hello!');

            $notes_bob = iterator_to_array(nostriphant\Stores\Store::query($events, ['ids' => [$bob_message()[1]['id']]]));


            expect($notes_bob)->toHaveLength(1);
            expect($notes_bob[0]->kind)->toBe(1);
            expect($notes_bob[0]->content)->toBe('Hello!');
            
        } catch (\Exception $e) {
            $transpher();
            throw $e;
        }

        $transpher();
    })->with([
        ['a71a415936f2dd70b777e5204c57e0df9a6dffef91b3c78c1aa24e54772e33c3', '6eeb5ad99e47115467d096e07c1c9b8b41768ab53465703f78017204adc5b0cc']
    ]);
    
});
