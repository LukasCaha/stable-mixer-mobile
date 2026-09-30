<?php

namespace StableMixer\Speech;

class Speech
{
    public function speak(string $text): void
    {
        if (! function_exists('nativephp_call') || trim($text) === '') {
            return;
        }

        nativephp_call('Speech.Speak', json_encode(['text' => $text]));
    }

    public function stop(): void
    {
        if (! function_exists('nativephp_call')) {
            return;
        }

        nativephp_call('Speech.Stop', '{}');
    }
}
