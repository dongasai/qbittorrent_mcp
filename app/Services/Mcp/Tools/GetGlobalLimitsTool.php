<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use PhpMcp\Server\Attributes\McpTool;
use Illuminate\Support\Facades\Log;

/**
 * qBittorrent 获取全局下载/上传限制工具
 *
 * 获取 qBittorrent 全局下载和上传速度限制设置
 */
class GetGlobalLimitsTool
{
    /**
     * 获取全局下载限制
     *
     * 获取 qBittorrent 设置的全局下载速度限制：
     * - 返回当前下载速度限制值
     * - 返回格式化的限制值（便于阅读）
     * - 包含单位转换和状态信息
     */
    #[McpTool(name: 'get_global_download_limit')]
    public function getGlobalDownloadLimit(): array
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
                $downloadLimit = $transferData['dl_rate_limit'] ?? 0;

                return [
                    'success' => true,
                    'type' => 'download',
                    'limit' => [
                        // 原始限制值（字节/秒）
                        'raw_limit' => $downloadLimit,

                        // 限制状态
                        'is_limited' => $downloadLimit > 0,

                        // 格式化的限制值
                        'formatted_limit' => $downloadLimit > 0 ? $this->formatBytes($downloadLimit) . '/s' : '无限制',

                        // 限制级别描述
                        'limit_level' => $this->getLimitLevel($downloadLimit),
                    ],

                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取全局下载限制失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取全局下载限制失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取全局上传限制
     *
     * 获取 qBittorrent 设置的全局上传速度限制：
     * - 返回当前上传速度限制值
     * - 返回格式化的限制值（便于阅读）
     * - 包含单位转换和状态信息
     */
    #[McpTool(name: 'get_global_upload_limit')]
    public function getGlobalUploadLimit(): array
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
                $uploadLimit = $transferData['up_rate_limit'] ?? 0;

                return [
                    'success' => true,
                    'type' => 'upload',
                    'limit' => [
                        // 原始限制值（字节/秒）
                        'raw_limit' => $uploadLimit,

                        // 限制状态
                        'is_limited' => $uploadLimit > 0,

                        // 格式化的限制值
                        'formatted_limit' => $uploadLimit > 0 ? $this->formatBytes($uploadLimit) . '/s' : '无限制',

                        // 限制级别描述
                        'limit_level' => $this->getLimitLevel($uploadLimit),
                    ],

                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取全局上传限制失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取全局上传限制失败: ' . $e->getMessage()
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

    /**
     * 根据限制值获取限制级别描述
     */
    private function getLimitLevel(int $limit): string
    {
        if ($limit <= 0) {
            return '无限制';
        } elseif ($limit < 1024 * 1024) { // < 1MB/s
            return '低速限制';
        } elseif ($limit < 10 * 1024 * 1024) { // < 10MB/s
            return '中速限制';
        } else { // >= 10MB/s
            return '高速限制';
        }
    }
}