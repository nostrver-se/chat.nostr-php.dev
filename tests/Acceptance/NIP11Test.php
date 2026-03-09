<?php

namespace nostriphant\RelayTests\Feature;

use nostriphant\TranspherTests\AcceptanceCase;
use nostriphant\NIP01\Key;

it('boots a relay instance, which responds with an NIP-11 information document on a "GET /" request', function() {
    $recipient = Key::fromHex('6eeb5ad99e47115467d096e07c1c9b8b41768ab53465703f78017204adc5b0cc');

    $data_dir = AcceptanceCase::data_dir('8088');

    $transpher = AcceptanceCase::start_transpher('8088', $data_dir, $recipient, []);
    
    list($protocol, $status, $headers, $body) = \nostriphant\Blossom\request('GET', AcceptanceCase::relay_url('http://', '8088') . '/', headers: ['Accept: application/nostr+json']);
    expect($status)->toBe('200');
    //$body = $this->expectRelayResponse('/', 200, 'application/nostr+json', headers:['Accept: application/nostr+json']);
    expect($body)->toBe(json_encode([
            'name' => 'Really relay',
            'description' => 'This is my dev relay',
            'pubkey' => '2b0d6f7a9c30264fed56ab9759761a47ce155bb04eea5ab47ab00dc4b9cb61c0',
            'contact' =>'transpher@nostriphant.dev',
            'supported_nips' => [1, 2, 9, 11, 12, 13, 16, 20, 22, 33, 45],
            'software' => "https://github.com/nostriphant/transpher",
            'version' => TRANSPHER_VERSION
    ]));
    
    $transpher();
});