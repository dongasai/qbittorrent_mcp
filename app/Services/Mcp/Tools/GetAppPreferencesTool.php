<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use PhpMcp\Server\Attributes\McpTool;
use Illuminate\Support\Facades\Log;

/**
 * qBittorrent 获取应用设置工具
 *
 * 获取 qBittorrent 应用的偏好设置，包括下载、上传、界面等各种配置
 */
class GetAppPreferencesTool
{
    /**
     * 获取 qBittorrent 应用设置
     *
     * 获取应用的完整偏好设置：
     * - 下载设置
     * - 上传设置
     * - 界面设置
     * - 连接设置
     * - 高级设置
     */
    #[McpTool(name: 'get_app_preferences')]
    public function getAppPreferences(): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent) {
                // 获取应用偏好设置
                $preferencesRequest = \PhpQbittorrent\Request\Application\GetPreferencesRequest::create();
                $preferencesResponse = $client->application()->getPreferences($preferencesRequest);

                if (!$preferencesResponse->isSuccess()) {
                    throw new \Exception('无法获取应用设置: ' . implode(', ', $preferencesResponse->getErrors()));
                }

                $preferences = $preferencesResponse->getData()['preferences'] ?? [];

                return [
                    'success' => true,
                    'preferences' => [
                        // 下载相关设置
                        'downloads' => [
                            'save_path' => $preferences['save_path'] ?? '',
                            'temp_path_enabled' => $preferences['temp_path_enabled'] ?? false,
                            'temp_path' => $preferences['temp_path'] ?? '',
                            'max_downloads' => $preferences['max_downloads'] ?? -1,
                            'max_active_downloads' => $preferences['max_active_downloads'] ?? -1,
                            'max_active_torrents' => $preferences['max_active_torrents'] ?? -1,
                            'max_active_uploads' => $preferences['max_active_uploads'] ?? -1,
                            'stop_tracker_timeout' => $preferences['stop_tracker_timeout'] ?? 5,
                            'max_connections_per_torrent' => $preferences['max_connections_per_torrent'] ?? 100,
                            'global_max_connections' => $preferences['global_max_connections'] ?? 500,
                        ],

                        // 速度限制设置
                        'speed_limits' => [
                            'dl_limit' => $preferences['dl_limit'] ?? 0,
                            'up_limit' => $preferences['up_limit'] ?? 0,
                            'alt_dl_limit' => $preferences['alt_dl_limit'] ?? 10000,
                            'alt_up_limit' => $preferences['alt_up_limit'] ?? 10000,
                            'alt_speeds_enabled' => $preferences['alt_speeds_enabled'] ?? false,
                        ],

                        // BitTorrent 设置
                        'bittorrent' => [
                            'dht' => $preferences['dht'] ?? true,
                            'pex' => $preferences['pex'] ?? true,
                            'lsd' => $preferences['lsd'] ?? true,
                            'encryption' => $preferences['encryption'] ?? 0,
                            'anonymous_mode' => $preferences['anonymous_mode'] ?? false,
                            'queueing_enabled' => $preferences['queueing_enabled'] ?? false,
                        ],

                        // 界面设置
                        'ui' => [
                            'locale' => $preferences['locale'] ?? 'en',
                            'delete_torrent_files' => $preferences['delete_torrent_files'] ?? false,
                            'confirm_when_deleting' => $preferences['confirm_when_deleting'] ?? true,
                            'speed_in_title_bar' => $preferences['speed_in_title_bar'] ?? false,
                        ],

                        // 连接设置
                        'connection' => [
                            'port' => $preferences['port'] ?? 8080,
                            'web_ui_port' => $preferences['web_ui_port'] ?? 8080,
                            'use_upnp' => $preferences['use_upnp'] ?? true,
                            'use_random_port' => $preferences['use_random_port'] ?? false,
                            'ssl_enabled' => $preferences['ssl_enabled'] ?? false,
                        ],

                        // 高级设置
                        'advanced' => [
                            'disk_cache' => $preferences['disk_cache'] ?? 64,
                            'disk_cache_ttl' => $preferences['disk_cache_ttl'] ?? 60,
                            'os_cache' => $preferences['os_cache'] ?? true,
                            'embedded_tracker' => $preferences['embedded_tracker'] ?? false,
                            'embedded_tracker_port' => $preferences['embedded_tracker_port'] ?? 9000,
                        ]
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取应用设置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取应用设置失败: ' . $e->getMessage()
            ];
        }
    }
}