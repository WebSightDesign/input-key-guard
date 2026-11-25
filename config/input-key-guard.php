<?php

return [
    /*
     | Maximum cumulative bytes of input keys/prefixes per request. Matches the
     | upstream Laravel fix (20 MB). Exceeding it yields HTTP 413.
     */
    'max_bytes' => (int) env('INPUT_KEY_GUARD_MAX_BYTES', 20 * 1024 * 1024),
];
