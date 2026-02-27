<?php

use nostriphant\TranspherTests\AcceptanceCase;

$transpher;
beforeAll(function() use (&$transpher) {
    $data_dir = AcceptanceCase::data_dir('8091');
    $sender_key = nostriphant\NIP01\Key::fromHex('a71a415936f2dd70b777e5204c57e0df9a6dffef91b3c78c1aa24e54772e33c3');
    $transpher = AcceptanceCase::start_transpher('8091', $data_dir, $sender_key, []);
});

describe('blossom', function() {
    

    it('is enabled and functional, simple GET request to an prewritten blob', function () {
        $data_dir = AcceptanceCase::data_dir('8091');

        $files_dir = $data_dir . '/files';
        is_dir($files_dir) || mkdir($files_dir);
        
        $hash = nostriphant\Blossom\writeFile($files_dir, 'Hello world!');
        expect($files_dir . '/' . $hash)->toBeFile();
        
        
        list($protocol, $status, $headers, $body) = nostriphant\Blossom\request('GET', AcceptanceCase::relay_url('http://', '8091') . '/' . $hash, authorization: ['t' => 'get', 'x' => $hash]);
        expect($status)->toBe('200');
        expect($body)->toBe('Hello world!');
    });
    
    it('uploads work', function () {
        $resource = tmpfile();
        fwrite($resource, 'Hello World');
        fseek($resource, 0);
        
        $expected_hash = hash('sha256', 'Hello World');
        $url = AcceptanceCase::relay_url('http://', '8091');
        
        list($protocol, $status, $headers, $body) = nostriphant\Blossom\request('PUT', $url . '/upload', upload_resource:$resource, authorization: ['t' => 'get', 'x' => $expected_hash]);
        expect($status)->toBe('201', $headers['x-reason']?? $body);
        
        $blob_descriptor = json_decode($body);
        expect($blob_descriptor)->not()->toBeNull($body);
        expect($blob_descriptor->url)->toBe($url . '/' . $expected_hash);
        expect($blob_descriptor->sha256)->toBe($expected_hash);
    });
    
});

afterAll(function() use (&$transpher) { $transpher(); });