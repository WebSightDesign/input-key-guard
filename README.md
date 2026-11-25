# wsd/input-key-guard

Mitigation for Laravel advisory **GHSA-8h64-cpq2-9h2g** on Laravel 9, 10 and 11 apps that cannot be upgraded yet.

## What it mitigates

`TransformsRequest::cleanArray()` (parent of `TrimStrings` and `ConvertEmptyStringsToNull`) rebuilds the full key prefix for every element it visits. A JSON body with one very long key holding a large array (about 590 KB) causes gigabytes of string building and ~30 s of CPU, unauthenticated.

This package adds a global middleware, prepended before `TrimStrings`, that walks the query string and the JSON/form body, totals the same key-prefix bytes using integer arithmetic only (no string copies, O(elements)), and throws `PostTooLargeException` (HTTP 413) once the total exceeds the limit. The default limit is 20 MB, matching the upstream fix.

## Install

```
composer require wsd/input-key-guard
```

The service provider is auto-discovered and prepends the middleware to the HTTP kernel. No app changes needed.

Optional config: `php artisan vendor:publish --tag=input-key-guard-config`, or set `INPUT_KEY_GUARD_MAX_BYTES` in `.env`.

## Scope

It covers **only** this one advisory. It is not a general WAF and does not replace upgrading.

## When to remove it

Upgrade to Laravel >= 12.74 or >= 13.38 (which contain the upstream fix), then `composer remove wsd/input-key-guard`. Until then, on those versions it is redundant but harmless.

## Installing on old Laravel with Composer 2.11

Composer's "block insecure" audit refuses to resolve advisory-affected `laravel/framework` releases. Client apps still on 9/10 may need `"config": {"audit": {"block-insecure": false}}` in their own composer.json; this package's `config` does not apply to dependents.
