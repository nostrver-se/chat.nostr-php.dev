<?php

namespace nostriphant\TranspherTests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class AcceptanceCase extends BaseTestCase
{   
    static public function unwrap(\nostriphant\NIP01\Key $recipient_key) {
        return function(array $gift) use ($recipient_key) {
            expect($gift['kind'])->toBe(1059);
            expect($gift['tags'])->toContain(['p', $recipient_key(\nostriphant\NIP01\Key::public())]);

            $seal = \nostriphant\NIP59\Gift::unwrap($recipient_key, \nostriphant\NIP01\Event::__set_state($gift));
            expect($seal->kind)->toBe(13);
            expect($seal->pubkey)->toBeString();
            expect($seal->content)->toBeString();

            $private_message = \nostriphant\NIP59\Seal::open($recipient_key, $seal);
            expect($private_message)->toHaveKey('id');
            expect($private_message)->toHaveKey('content');
            return $private_message->content;
        };
    }
    
    static function client_log(string $client, string $pubkey) {
        $handle = fopen(ROOT_DIR . '/logs/' . $client . '.log', 'w');
        $log = fn(string $message) => fwrite($handle, $message . PHP_EOL);

        $log('>>> Starting log for client ' . $client . ' ('.$pubkey.')');

        return $log;
    }
    
    static function createListener(callable $unwrapper, array &$alices_expected_messages, string $data_dir, callable $alice_log) {
        return function (\nostriphant\NIP01\Message $message, callable $stop) use ($unwrapper, &$alices_expected_messages, $data_dir, $alice_log) {
            $message_log = fn(string $log_message) => $alice_log(substr(sha1($message), 0, 6) . ' - ' . $log_message);
            
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
                            if ($unwrapper($message->payload[1]) !== $expected_payload[1]) {
                                $message_log('Expected message "'. $expected_payload[1]. '", received "'.$unwrapper($message->payload[1]).'", skipping...');
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
            $alice_log('Expected messages remaining ' . count($alices_expected_messages));
            $alice_log(var_export($alices_expected_messages, true));
            if (count($alices_expected_messages) === 0) {
                $stop();
            }
        };
    }
}
