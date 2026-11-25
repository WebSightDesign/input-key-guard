<?php

namespace Wsd\InputKeyGuard\Tests;

use Illuminate\Contracts\Http\Kernel;
use Wsd\InputKeyGuard\GuardInputKeyBytes;

class GuardInputKeyBytesTest extends TestCase
{
    protected function limit(int $bytes): void
    {
        config(['input-key-guard.max_bytes' => $bytes]);
    }

    protected function globalMiddleware($kernel): array
    {
        $property = new \ReflectionProperty(\Illuminate\Foundation\Http\Kernel::class, 'middleware');
        $property->setAccessible(true);

        return $property->getValue($kernel);
    }

    public function test_upstream_attack_shape_returns_413_quickly(): void
    {
        $payload = [str_repeat('a', 295000) => range(1, 147000)];

        $start = microtime(true);
        $response = $this->postJson('/echo', $payload);
        $elapsed = microtime(true) - $start;

        $response->assertStatus(413);
        $this->assertLessThan(2.0, $elapsed, "Guarded attack took {$elapsed}s");
        fwrite(STDERR, sprintf("\n[timing] guarded upstream-shape attack: %.3fs\n", $elapsed));
    }

    public function test_normal_json_passes_unchanged_and_is_still_trimmed(): void
    {
        $this->postJson('/echo', ['name' => '  Bob ', 'empty' => '', 'nested' => ['a' => [1, 2]]])
            ->assertOk()
            ->assertExactJson(['all' => ['name' => 'Bob', 'empty' => null, 'nested' => ['a' => [1, 2]]]]);
    }

    public function test_normal_form_and_query_pass(): void
    {
        $this->post('/echo?x=1', ['a' => 'b'])->assertOk()->assertJsonPath('all.a', 'b')->assertJsonPath('all.x', '1');
        $this->get('/echo?q[]=1&q[]=2')->assertOk()->assertJsonPath('all.q', ['1', '2']);
    }

    public function test_nested_boundary_exact_limit_passes_one_over_fails(): void
    {
        // 'a' = 1; children prefix 'a.' (2) + 'b'/'c' (1) each = 3 + 3 -> 7 bytes
        $payload = ['a' => ['b' => 1, 'c' => 2]];

        $this->limit(7);
        $this->postJson('/echo', $payload)->assertOk();

        $this->limit(6);
        $this->postJson('/echo', $payload)->assertStatus(413);
    }

    public function test_deeper_prefix_lengths_counted(): void
    {
        // a=1; a.b: prefix 2 + 1 = 3 (total 4); a.b.c: prefix 4 + 1 = 5 (total 9)
        $payload = ['a' => ['b' => ['c' => 1]]];

        $this->limit(9);
        $this->postJson('/echo', $payload)->assertOk();

        $this->limit(8);
        $this->postJson('/echo', $payload)->assertStatus(413);
    }

    public function test_query_string_keys_are_counted(): void
    {
        $this->limit(5);
        $this->get('/echo?abcde=1')->assertOk();
        $this->get('/echo?abcdef=1')->assertStatus(413);
    }

    public function test_non_json_form_body_is_counted(): void
    {
        $this->limit(5);
        $this->post('/echo', ['abcde' => '1'])->assertOk();
        $this->post('/echo', ['abcdef' => '1'])->assertStatus(413);
    }

    public function test_provider_prepends_middleware_first_and_only_once(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $global = $this->globalMiddleware($kernel);

        $this->assertSame(GuardInputKeyBytes::class, $global[0]);
        $this->assertSame(1, count(array_keys($global, GuardInputKeyBytes::class, true)));

        (new \Wsd\InputKeyGuard\InputKeyGuardServiceProvider($this->app))->boot();
        $this->assertSame(1, count(array_keys($this->globalMiddleware($kernel), GuardInputKeyBytes::class, true)));
    }
}
