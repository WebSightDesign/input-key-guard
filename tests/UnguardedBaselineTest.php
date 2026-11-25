<?php

namespace Wsd\InputKeyGuard\Tests;

use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase as Orchestra;
use Wsd\InputKeyGuard\GuardInputKeyBytes;

/**
 * No provider registered: shows the unguarded attack is slow and huge (reduced
 * size so it stays bounded), versus the guard rejecting the same payload.
 */
class UnguardedBaselineTest extends Orchestra
{
    public function test_unguarded_reduced_attack_is_slow_guard_is_fast(): void
    {
        // 100 KB key x 30,000 items = ~3 GB of prefix string building.
        $body = json_encode([str_repeat('a', 100000) => range(1, 30000)]);
        $make = function () use ($body) {
            return Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        };

        $start = microtime(true);
        try {
            (new TrimStrings())->handle($make(), fn ($r) => 'ok');
        } catch (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            $this->markTestSkipped('Framework already contains the upstream fix.');
        }
        $unguarded = microtime(true) - $start;

        $start = microtime(true);
        $threw = false;
        try {
            (new GuardInputKeyBytes())->handle($make(), fn ($r) => 'ok');
        } catch (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            $threw = true;
        }
        $guarded = microtime(true) - $start;

        fwrite(STDERR, sprintf("\n[timing] reduced attack unguarded TrimStrings: %.3fs ; guard: %.4fs\n", $unguarded, $guarded));

        $this->assertTrue($threw);
        $this->assertGreaterThan($guarded * 5, $unguarded);
    }
}
