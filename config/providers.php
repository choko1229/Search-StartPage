<?php
declare(strict_types=1);

return [
    'web' => [
        ['id' => 'google', 'name' => 'Google', 'url' => 'https://www.google.com/search?q={query}', 'prefix' => 'g', 'icon' => 'G'],
        ['id' => 'bing', 'name' => 'Bing', 'url' => 'https://www.bing.com/search?q={query}', 'prefix' => 'b', 'icon' => 'B'],
        ['id' => 'ddg', 'name' => 'DuckDuckGo', 'url' => 'https://duckduckgo.com/?q={query}', 'prefix' => 'd', 'icon' => 'D'],
        ['id' => 'yahoo', 'name' => 'Yahoo! JAPAN', 'url' => 'https://search.yahoo.co.jp/search?p={query}', 'prefix' => 'y', 'icon' => 'Y'],
        ['id' => 'youtube', 'name' => 'YouTube', 'url' => 'https://www.youtube.com/results?search_query={query}', 'prefix' => 'yt', 'icon' => '▶'],
        ['id' => 'github', 'name' => 'GitHub', 'url' => 'https://github.com/search?q={query}', 'prefix' => 'gh', 'icon' => '⌘'],
        ['id' => 'x', 'name' => 'X', 'url' => 'https://x.com/search?q={query}', 'prefix' => 'x', 'icon' => 'X'],
        ['id' => 'booth', 'name' => 'BOOTH', 'url' => 'https://booth.pm/ja/search/{query}', 'prefix' => 'bo', 'icon' => 'B'],
        ['id' => 'wikipedia', 'name' => 'Wikipedia', 'url' => 'https://ja.wikipedia.org/w/index.php?search={query}', 'prefix' => 'w', 'icon' => 'W'],
        ['id' => 'amazon', 'name' => 'Amazon', 'url' => 'https://www.amazon.co.jp/s?k={query}', 'prefix' => 'a', 'icon' => 'a'],
    ],
    'ai' => [
        ['id' => 'chatgpt', 'name' => 'ChatGPT', 'url' => 'https://chatgpt.com/?q={query}', 'prefix' => 'ai', 'icon' => '✳'],
        ['id' => 'claude', 'name' => 'Claude', 'url' => 'https://claude.ai/new', 'prefix' => 'cl', 'icon' => '✴', 'copy' => true],
        ['id' => 'grok', 'name' => 'Grok', 'url' => 'https://grok.com/?q={query}', 'prefix' => 'gr', 'icon' => 'G'],
        ['id' => 'gemini', 'name' => 'Gemini', 'url' => 'https://gemini.google.com/app', 'prefix' => 'ge', 'icon' => '✦', 'copy' => true],
    ],
];
