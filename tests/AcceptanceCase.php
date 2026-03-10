<?php

namespace nostriphant\TranspherTests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class AcceptanceCase extends BaseTestCase
{
    static function createListener(string $client, \nostriphant\NIP01\Key $recipient, ?array &$alices_expected_messages) {
        $alices_expected_messages = [];
        
        $handle = fopen(ROOT_DIR . '/logs/' . $client . '.log', 'w');
        $logger = fn(string $message) => fwrite($handle, $message . PHP_EOL);

        $logger('>>> Starting log for client ' . $client . ' ('.$recipient(\nostriphant\NIP01\Key::public()).')');
        return function (\nostriphant\NIP01\Message $message, callable $stop) use ($recipient, &$alices_expected_messages, $logger) {
            $message_log = fn(string $log_message) => $logger(substr(sha1($message), 0, 6) . ' - ' . $log_message);
            
            $message_log('Received ' . $message);

            $remaining = [];
            foreach ($alices_expected_messages as $expected_message) {
                $expected_type = $expected_message[0];
                $expected_payload = array_slice($expected_message, 1);

                if ($expected_type !== $message->type) {
                    $message_log('Expected type ' . $expected_type . ' received ' . $message->type . ', skipping...');
                    $remaining[] = $expected_message;
                    continue;
                }

                switch ($message->type) {
                    case 'EVENT':
                        if ($message->payload[0] !== $expected_payload[0]) {
                            $remaining[] = $expected_message;
                            $message_log('Expected subscription id ' . $expected_payload[0] . ' received ' . $message->payload[0] . ', skipping...');
                        } elseif ($message->payload[1]['kind'] === 1059) {
                            $gift = $message->payload[1];
                            expect($gift['tags'])->toContain(['p', $recipient(\nostriphant\NIP01\Key::public())]);

                            $seal = \nostriphant\NIP59\Gift::unwrap($recipient, \nostriphant\NIP01\Event::__set_state($gift));
                            expect($seal->kind)->toBe(13);
                            expect($seal->pubkey)->toBeString();
                            expect($seal->content)->toBeString();

                            $private_message = \nostriphant\NIP59\Seal::open($recipient, $seal);
                            expect($private_message)->toHaveKey('id');
                            expect($private_message)->toHaveKey('content');
                            if ($private_message->content !== $expected_payload[1]) {
                                $message_log('Expected message "'. $expected_payload[1]. '", received "'.$private_message->content.'", skipping...');
                                $remaining[] = $expected_message;
                            }
                        } elseif ($message->payload[1]['content'] !== $expected_payload[1]) {
                                $message_log('Expected message "'. $expected_payload[1]. '", received "'. $message->payload[1]['content'].'", skipping...');
                                $remaining[] = $expected_message;
                        } else {
                            $message_log('OK, removing expected message from stack...');
                        }
                        break;

                    default:
                        if ($message->payload !== $expected_payload) {
                            $message_log('Expected payload '.var_export($expected_payload, true) .', skipping ...');
                            $remaining[] = $expected_message;
                        } else {
                            $message_log('OK, removing expected message from stack...');
                        }
                        break;

                }

            }

            $alices_expected_messages = $remaining;
            $logger('Expected messages remaining ' . count($alices_expected_messages));
            $logger(var_export($alices_expected_messages, true));
            if (count($alices_expected_messages) === 0) {
                $stop();
            }
        };
    }
}
