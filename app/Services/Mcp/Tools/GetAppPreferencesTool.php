<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * qBittorrent 获取应用设置工具
 *
 * 获取 qBittorrent 应用的偏好设置，包括下载、上传、界面等各种配置
 */
#[IsReadOnly]
#[IsIdempotent]
class GetAppPreferencesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取 qBittorrent 应用的完整偏好设置，包括下载、上传、界面、连接和高级配置';

    /**
     * The tool's name.
     */
    protected string $name = 'get_app_preferences';

    /**
     * The tool's title.
     */
    protected string $title = 'Get qBittorrent App Preferences';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            // 此工具不需要输入参数
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            $preferencesData = $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent) {
                // 获取应用偏好设置
                $preferencesRequest = \PhpQbittorrent\Request\Application\GetPreferencesRequest::create();
                $preferencesResponse = $client->application()->getPreferences($preferencesRequest);

                if (! $preferencesResponse->isSuccess()) {
                    throw new \Exception('无法获取应用设置: '.implode(', ', $preferencesResponse->getErrors()));
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
                        ],
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false,
                    ],
                ];
            });

            return Response::text(json_encode($preferencesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } catch (\Exception $e) {
            Log::error('获取应用设置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::text('获取应用设置失败: '.$e->getMessage());
        }
    }
}
