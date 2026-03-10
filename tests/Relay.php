<?php

namespace nostriphant\TranspherTests;

class Relay {
    private Feature\Process $process;
    
    public function __construct(public string $socket, public array $env) {
        $cmd = [PHP_BINARY, ROOT_DIR . DIRECTORY_SEPARATOR . 'relay.php', $socket];
        list($scheme, $uri) = explode(":", $socket, 2);
        $this->process = new Feature\Process('relay-' . substr(sha1($socket), 0, 6), $cmd, $env, fn(string $line) => str_contains($line, 'Listening on http:' . $uri . '/'));
    }
    
    public function __invoke(): mixed {
        return call_user_func($this->process);
    }
}
