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
 * qBittorrent 获取全局速度限制工具
 *
 * 获取 qBittorrent 全局上传和下载速度限制设置
 */
#[IsReadOnly]
#[IsIdempotent]
class GetGlobalLimitsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取 qBittorrent 全局上传和下载速度限制，返回原始值、格式化值和限制级别';

    /**
     * The tool's name.
     */
    protected string $name = 'get_global_limits';

    /**
     * The tool's title.
     */
    protected string $title = 'Get Global Speed Limits';

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

            $limitsData = $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent) {
                // 获取全局传输信息
                $transferInfoRequest = \PhpQbittorrent\Request\Transfer\GetGlobalTransferInfoRequest::create();
                $transferInfoResponse = $client->transfer()->getGlobalTransferInfo($transferInfoRequest);

                if (! $transferInfoResponse->isSuccess()) {
                    throw new \Exception('无法获取全局传输信息: '.implode(', ', $transferInfoResponse->getErrors()));
                }

                $transferData = $transferInfoResponse->getData();
                $downloadLimit = $transferData['dl_rate_limit'] ?? 0;
                $uploadLimit = $transferData['up_rate_limit'] ?? 0;

                return [
                    'success' => true,
                    'limits' => [
                        'download' => [
                            // 原始限制值（字节/秒）
                            'raw_limit' => $downloadLimit,

                            // 限制状态
                            'is_limited' => $downloadLimit > 0,

                            // 格式化的限制值
                            'formatted_limit' => $downloadLimit > 0 ? $this->formatBytes($downloadLimit).'/s' : '无限制',

                            // 限制级别描述
                            'limit_level' => $this->getLimitLevel($downloadLimit),
                        ],

                        'upload' => [
                            // 原始限制值（字节/秒）
                            'raw_limit' => $uploadLimit,

                            // 限制状态
                            'is_limited' => $uploadLimit > 0,

                            // 格式化的限制值
                            'formatted_limit' => $uploadLimit > 0 ? $this->formatBytes($uploadLimit).'/s' : '无限制',

                            // 限制级别描述
                            'limit_level' => $this->getLimitLevel($uploadLimit),
                        ],

                        // 总体状态
                        'summary' => [
                            'has_any_limit' => $downloadLimit > 0 || $uploadLimit > 0,
                            'both_limited' => $downloadLimit > 0 && $uploadLimit > 0,
                            'both_unlimited' => $downloadLimit == 0 && $uploadLimit == 0,
                        ],
                    ],

                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false,
                    ],
                ];
            });

            return Response::text(json_encode($limitsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } catch (\Exception $e) {
            Log::error('获取全局速度限制失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::text('获取全局速度限制失败: '.$e->getMessage());
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
