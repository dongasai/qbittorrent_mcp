<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use PhpMcp\Server\Attributes\McpTool;
use Illuminate\Support\Facades\Log;

/**
 * qBittorrent 获取主要数据工具
 *
 * 获取 qBittorrent 的全局传输信息和统计数据
 */
class GetMainDataTool
{
    /**
     * 获取 qBittorrent 主要数据
     *
     * 获取全局传输信息：
     * - 下载速度
     * - 上传速度
     * - 全局下载限制
     * - 全局上传限制
     * - 已下载总量
     * - 已上传总量
     * - 连接统计
     */
    #[McpTool(name: 'get_main_data')]
    public function getMainData(): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent) {
                // 获取全局传输信息
                $transferInfoRequest = \PhpQbittorrent\Request\Transfer\GetGlobalTransferInfoRequest::create();
                $transferInfoResponse = $client->transfer()->getGlobalTransferInfo($transferInfoRequest);

                if (!$transferInfoResponse->isSuccess()) {
                    throw new \Exception('无法获取全局传输信息: ' . implode(', ', $transferInfoResponse->getErrors()));
                }

                $transferData = $transferInfoResponse->getData();

                return [
                    'success' => true,
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
                        'download_speed' => $this->formatBytes($transferData['dl_info_speed'] ?? 0) . '/s',
                        'upload_speed' => $this->formatBytes($transferData['up_info_speed'] ?? 0) . '/s',
                        'download_limit' => ($transferData['dl_rate_limit'] ?? 0) > 0 ? $this->formatBytes($transferData['dl_rate_limit']) . '/s' : '无限制',
                        'upload_limit' => ($transferData['up_rate_limit'] ?? 0) > 0 ? $this->formatBytes($transferData['up_rate_limit']) . '/s' : '无限制',
                    ],

                    // 格式化的数据量信息
                    'formatted_data' => [
                        'total_downloaded' => $this->formatBytes($transferData['dl_info_data'] ?? 0),
                        'total_uploaded' => $this->formatBytes($transferData['up_info_data'] ?? 0),
                    ],

                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取主要数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取主要数据失败: ' . $e->getMessage()
            ];
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

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}