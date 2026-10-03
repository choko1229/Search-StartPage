<?php
declare(strict_types=1);

return [
    'installed' => false,
    'site' => ['name' => 'search.choko1229.net', 'url' => 'http://localhost:8080'],
    'database' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => '', 'user' => '', 'password' => ''],
    'discord' => ['client_id' => '', 'client_secret' => ''],
    'login_rate_limit' => ['attempts' => 20, 'window_seconds' => 60],
    'backgrounds' => ['max_bytes' => 0], // 0 = no account-wide cap; per-file limits still apply.
    'weather' => ['enabled' => true, 'mode' => 'non-commercial', 'api_key' => ''], // Open-Meteo; customer mode for a commercial subscription.
    'initial_admin_discord_id' => '',
    'encryption_key' => '',
    'session' => ['name' => 'search_session', 'secure' => true, 'same_site' => 'Lax'],
    'ffmpeg_path' => '',
];
