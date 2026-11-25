<?php

namespace Wsd\InputKeyGuard;

use Closure;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

/**
 * Counts the bytes TransformsRequest::cleanArray() would spend on key prefixes
 * (GHSA-8h64-cpq2-9h2g) without building any concatenated strings.
 */
class GuardInputKeyBytes
{
    /** @var int */
    protected $keyBytes = 0;

    /** @var int */
    protected $maxKeyBytes = 0;

    public function handle(Request $request, Closure $next)
    {
        $this->keyBytes = 0;
        $this->maxKeyBytes = (int) config('input-key-guard.max_bytes', 20 * 1024 * 1024);

        $this->walk($request->query->all(), 0);

        if ($request->isJson()) {
            $this->walk($request->json()->all(), 0);
        } else {
            $this->walk($request->request->all(), 0);
        }

        return $next($request);
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    protected function walk(array $data, int $prefixLength): void
    {
        foreach ($data as $key => $value) {
            $keyLength = strlen((string) $key);
            $this->keyBytes += $prefixLength + $keyLength;

            if ($this->keyBytes > $this->maxKeyBytes) {
                throw new PostTooLargeException('Input keys exceed the maximum allowed size.');
            }

            if (is_array($value)) {
                $this->walk($value, $prefixLength + $keyLength + 1);
            }
        }
    }
}
