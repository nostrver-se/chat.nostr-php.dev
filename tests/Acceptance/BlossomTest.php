<?php

use nostriphant\TranspherTests\Transpher;

$transpher;
beforeAll(function() use (&$transpher) {
    $sender_key = nostriphant\NIP01\Key::fromHex('a71a415936f2dd70b777e5204c57e0df9a6dffef91b3c78c1aa24e54772e33c3');
    $transpher = new Transpher('8091', $sender_key, []);
});

describe('blossom', function() use (&$transpher) {
    

    it('is enabled and functional, simple GET request to an prewritten blob', function () use (&$transpher) {
        $data_dir = $transpher->data_directory;

        $files_dir = $data_dir . '/files';
        is_dir($files_dir) || mkdir($files_dir);
        
        $hash = nostriphant\Blossom\writeFile($files_dir, 'Hello world!');
        expect($files_dir . '/' . $hash)->toBeFile();
        
        
        list($protocol, $status, $headers, $body) = nostriphant\Blossom\request('GET', $transpher->url . '/' . $hash, authorization: ['t' => 'get', 'x' => $hash]);
        expect($status)->toBe('200');
        expect($body)->toBe('Hello world!');
    });
    
    it('uploads work', function () use (&$transpher) {
        $resource = tmpfile();
        fwrite($resource, 'Hello World');
        fseek($resource, 0);
        
        $expected_hash = hash('sha256', 'Hello World');
        $url = $transpher->url;
        
        list($protocol, $status, $headers, $body) = nostriphant\Blossom\request('PUT', $url . '/upload', upload_resource:$resource, authorization: ['t' => 'get', 'x' => $expected_hash]);
        expect($status)->toBe('201', $headers['x-reason']?? $body);
        
        $blob_descriptor = json_decode($body);
        expect($blob_descriptor)->not()->toBeNull($body);
        expect($blob_descriptor->url)->toBe($url . '/' . $expected_hash);
        expect($blob_descriptor->sha256)->toBe($expected_hash);
    });
    
});

afterAll(function() use (&$transpher) { $transpher(); });