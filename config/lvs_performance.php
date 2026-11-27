<?php

return [
    // Enable in production by setting LVS_PERFORMANCE_ENABLED=true in .env.
    // Log only slow requests; no query SQL, bindings or personal data.
    'enabled' => (bool) env('LVS_PERFORMANCE_ENABLED', false),
    'slow_request_ms' => (int) env('LVS_PERFORMANCE_SLOW_MS', 1000),
];
