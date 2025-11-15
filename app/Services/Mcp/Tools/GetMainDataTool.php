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
 * qBittorrent 获取主要数据工具
 *
 * 获取 qBittorrent 的全局传输信息和统计数据，包括：
 * - 实时下载/上传速度
 * - 全局速度限制设置
 * - 累计下载/上传数据量
 * - 连接状态和节点信息
 * - 格式化的可读数据展示
 */
#[IsReadOnly]
#[IsIdempotent]
class GetMainDataTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取 qBittorrent 的全局传输信息和统计数据，包括速度、限制、数据量和连接状态';

    /**
     * The tool's name.
     */
    protected string $name = 'get_main_data';

    /**
     * The tool's title.
     */
    protected string $title = 'Get qBittorrent Main Data';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        // 此工具不需要输入参数
        return [];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // 此工具不需要输入参数，但需要声明 request 参数以符合接口规范
        unset($request);
        try {
            $qbittorrent = QbittorrentService::getInstance();

            $mainData = $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent) {
                // 获取全局传输信息
                $transferInfoRequest = \PhpQbittorrent\Request\Transfer\GetGlobalTransferInfoRequest::create();
                $transferInfoResponse = $client->transfer()->getGlobalTransferInfo($transferInfoRequest);

                if (! $transferInfoResponse->isSuccess()) {
                    throw new \Exception('无法获取全局传输信息: '.implode(', ', $transferInfoResponse->getErrors()));
                }

                $transferData = $transferInfoResponse->getData();

                return [
                    'transfer_info' => [
                        // 速度信息
                        'download_speed' => $transferData['dl_info_speed'] ?? 0,
                        'upload_speed' => $transferData['up_info_speed'] ?? 0,

                        // 限制信息
                        'download_limit' => $transferData['dl_rate_limit'] ?? 0,
                        'upload_limit' => $transferData['up_rate_limit'] ?? 0,

                        // 传输统计
                        'total_downloaded' => $transferData['dl_info_data'] ?? 0,
                        'total_uploaded' => $transferData['up_info_data'] ?? 0,

                        // 连接信息
                        'connection_status' => $transferData['connection_status'] ?? 'unknown',
                        'connected_peers' => $transferData['dht_nodes'] ?? 0,
                        'dht_nodes' => $transferData['dht_nodes'] ?? 0,

                        // 外部地址信息
                        'last_external_address_v4' => $transferData['last_external_address_v4'] ?? '',
                        'last_external_address_v6' => $transferData['last_external_address_v6'] ?? '',
                    ],

                    // 格式化的速度信息（便于阅读）
                    'formatted_speeds' => [
                        'download_speed' => $this->formatBytes($transferData['dl_info_speed'] ?? 0).'/s',
                        'upload_speed' => $this->formatBytes($transferData['up_info_speed'] ?? 0).'/s',
                        'download_limit' => ($transferData['dl_rate_limit'] ?? 0) > 0 ? $this->formatBytes($transferData['dl_rate_limit']).'/s' : '无限制',
                        'upload_limit' => ($transferData['up_rate_limit'] ?? 0) > 0 ? $this->formatBytes($transferData['up_rate_limit']).'/s' : '无限制',
                    ],

                    // 格式化的数据量信息
                    'formatted_data' => [
                        'total_downloaded' => $this->formatBytes($transferData['dl_info_data'] ?? 0),
                        'total_uploaded' => $this->formatBytes($transferData['up_info_data'] ?? 0),
                    ],

                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false,
                    ],
                ];
            });

            return Response::text(json_encode($mainData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } catch (\Exception $e) {
            Log::error('获取主要数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::error('获取主要数据失败: '.$e->getMessage());
        }
    }

    /**
     * 格式化字节数为可读格式
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
