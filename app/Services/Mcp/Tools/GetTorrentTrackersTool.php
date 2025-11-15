<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Support\Facades\Log;

/**
 * 获取种子Trackers信息工具
 *
 * 专门用于获取指定种子的Tracker信息，包括：
 * - Tracker URL和工作状态
 * - 连接数、种子数、下载数
 * - Tracker消息和层级信息
 */
class GetTorrentTrackersTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取指定种子的所有Tracker信息，包括URL、状态、连接数等';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $hash = $request->get('hash');
        $result = $this->execute($hash);

        if (!$result['success']) {
            return Response::error($result['error']);
        }

        return Response::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'hash' => $schema->string()
                ->description('种子的SHA1哈希值')
                ->required(),
        ];
    }
    /**
     * 获取torrent trackers
     *
     * 获取指定种子的所有Tracker信息：
     * - 返回Tracker URL、状态、连接数等
     * - 显示每个Tracker的种子和下载数
     * - 包含Tracker的工作状态信息
     */
    private function execute(string $hash): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash, $qbittorrent) {
                // 使用通用的 GET 方法来获取 trackers 信息
                $response = $client->torrents()->get('/trackers', ['hash' => $hash]);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子Trackers: ' . implode(', ', $response->getErrors()));
                }

                $trackersData = $response->getData() ?? [];
                $result = [];

                // 解析响应数据
                if (is_array($trackersData)) {
                    foreach ($trackersData as $tracker) {
                        $result[] = [
                            'url' => $tracker['url'] ?? '',
                            'status' => $tracker['status'] ?? 0,
                            'status_description' => $this->getTrackerStatusDescription($tracker['status'] ?? 0),
                            'message' => $tracker['msg'] ?? '',
                            'peers' => $tracker['num_peers'] ?? 0,
                            'seeds' => $tracker['num_seeds'] ?? 0,
                            'limit' => $tracker['limit'] ?? 0,
                            'downloaded' => $tracker['downloaded'] ?? 0,
                            'tier' => $tracker['tier'] ?? 0,
                        ];
                    }
                }

                return [
                    'success' => true,
                    'torrent_hash' => $hash,
                    'trackers' => $result,
                    'tracker_count' => count($result),
                    'working_trackers' => count(array_filter($result, fn($t) => $t['status'] === 0)),
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取种子Trackers失败', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取种子Trackers失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取Tracker状态描述
     */
    private function getTrackerStatusDescription(int $status): string
    {
        return match($status) {
            0 => 'Tracker正常工作',
            1 => 'Tracker正在更新',
            2 => 'Tracker未更新',
            3 => 'Tracker不可用',
            4 => 'Tracker已禁用',
            default => '未知状态'
        };
    }
}