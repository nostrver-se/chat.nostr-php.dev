<?php

namespace nostriphant\TranspherTests;

readonly class Agent {
    
    private Feature\Process $process;
    
    public function __construct(public string $port, public array $env) {
        $cmd = [PHP_BINARY, ROOT_DIR . DIRECTORY_SEPARATOR . 'agent.php', $port];
        $this->process = new Feature\Process('agent-' . $port, $cmd, $env, fn(string $line) => str_contains($line, 'Listening to relay...'));
    }
    
    public function __invoke(): mixed {
        return call_user_func($this->process);
    }
}
