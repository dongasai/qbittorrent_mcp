<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/qbittorrent', \App\Mcp\Servers\qBittorrentServer::class);
Mcp::local('qbittorrent', \App\Mcp\Servers\qBittorrentServer::class);

/**
 * echo "mcp规范的json字符串" | php artisan mcp:start qbittorrent
 */
