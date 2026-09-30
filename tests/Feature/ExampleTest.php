<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_a_browser_is_told_the_app_runs_on_the_phone(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Stable Mixer companion runs on the phone');
    }
}
