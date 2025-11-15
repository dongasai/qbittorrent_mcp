<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * qBittorrent 种子属性工具
 *
 * 获取指定种子的详细属性信息：
 *
 * 参数：
 * - hash - 种子的40位哈希值 (必需)
 *
 * 返回信息包括：
 * - 基本信息：名称、大小、进度、状态等
 * - 网络信息：下载/上传速度、连接数、做种数等
 * - 文件列表：包含所有文件的索引、名称、大小、优先级
 * - 存储信息：保存路径、分类、标签
 * - 时间信息：添加时间、完成时间、活动时间
 *
 * 注意：需要种子必须在 qBittorrent 中存在
 */
#[IsReadOnly]
class GetTorrentPropertiesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取指定种子的详细属性信息，包括基本信息、网络信息、文件列表等';

    /**
     * The tool's name.
     */
    protected string $name = 'get_torrent_properties';

    /**
     * The tool's title.
     */
    protected string $title = 'Get Torrent Properties';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'hash' => $schema->string('种子的40位哈希值')
                ->description('要查询属性的种子哈希值（40位十六进制字符）')
                ->pattern('^[a-fA-F0-9]{40}$')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $hash = $request->string('hash');

        try {
            $qbittorrent = QbittorrentService::getInstance();

            $torrentData = $qbittorrent->executeWithAuth(function ($client) use ($hash) {
                // 直接使用简化的API获取种子列表
                $response = $client->torrents()->get('/info', []);

                if (! $response->isSuccess()) {
                    throw new \Exception('无法获取种子列表: '.implode(', ', $response->getErrors()));
                }

                $torrents = json_decode($response->getRawResponse(), true);
                $targetTorrent = null;

                foreach ($torrents as $torrent) {
                    if ($torrent['hash'] === $hash) {
                        $targetTorrent = $torrent;
                        break;
                    }
                }

                if (! $targetTorrent) {
                    throw new \Exception("未找到哈希为 {$hash} 的种子");
                }

                // 获取种子属性
                $properties = $client->torrents()->getTorrentProperties($hash);

                // 获取种子文件列表
                try {
                    $torrentFiles = $client->torrents()->getTorrentFiles($hash);
                    $files = [];
                    foreach ($torrentFiles as $file) {
                        $files[] = [
                            'index' => $file['index'] ?? 0,
                            'name' => $file['name'] ?? '',
                            'size' => $file['size'] ?? 0,
                            'formatted_size' => $this->formatBytes($file['size'] ?? 0),
                            'progress' => $file['progress'] ?? 0,
                            'progress_percentage' => round(($file['progress'] ?? 0) * 100, 2).'%',
                            'priority' => $file['priority'] ?? 0,
                            'is_seed' => $file['is_seed'] ?? false,
                            'piece_range' => $file['piece_range'] ?? '',
                            'availability' => $file['availability'] ?? 0,
                        ];
                    }
                } catch (\Exception $e) {
                    // 如果获取文件列表失败，使用空数组
                    $files = [];
                }

                // 注意：当前版本的API中没有获取tracker的方法，暂时留空
                $trackers = [];

                return [
                    'torrent_info' => [
                        'hash' => $targetTorrent['hash'] ?? '',
                        'name' => $targetTorrent['name'] ?? '',
                        'size' => $targetTorrent['size'] ?? 0,
                        'formatted_size' => $this->formatBytes($targetTorrent['size'] ?? 0),
                        'progress' => $targetTorrent['progress'] ?? 0,
                        'progress_percentage' => round(($targetTorrent['progress'] ?? 0) * 100, 2).'%',
                        'state' => $targetTorrent['state'] ?? '',
                        'category' => $targetTorrent['category'] ?? '',
                        'tags' => $targetTorrent['tags'] ?? '',
                        'save_path' => $targetTorrent['save_path'] ?? '',
                        'added_time' => $targetTorrent['added_on'] ?? 0,
                        'completion_time' => $targetTorrent['completion_on'] ?? 0,
                        'download_speed' => $targetTorrent['dl_speed'] ?? 0,
                        'formatted_download_speed' => $this->formatBytes($targetTorrent['dl_speed'] ?? 0).'/s',
                        'upload_speed' => $targetTorrent['up_speed'] ?? 0,
                        'formatted_upload_speed' => $this->formatBytes($targetTorrent['up_speed'] ?? 0).'/s',
                        'downloaded' => $targetTorrent['downloaded'] ?? 0,
                        'formatted_downloaded' => $this->formatBytes($targetTorrent['downloaded'] ?? 0),
                        'uploaded' => $targetTorrent['uploaded'] ?? 0,
                        'formatted_uploaded' => $this->formatBytes($targetTorrent['uploaded'] ?? 0),
                        'ratio' => $targetTorrent['ratio'] ?? 0,
                        'dl_limit' => $targetTorrent['dl_limit'] ?? 0,
                        'formatted_dl_limit' => ($targetTorrent['dl_limit'] ?? 0) > 0 ? $this->formatBytes($targetTorrent['dl_limit'] ?? 0).'/s' : '无限制',
                        'up_limit' => $targetTorrent['up_limit'] ?? 0,
                        'formatted_up_limit' => ($targetTorrent['up_limit'] ?? 0) > 0 ? $this->formatBytes($targetTorrent['up_limit'] ?? 0).'/s' : '无限制',
                        'priority' => $targetTorrent['priority'] ?? 0,
                        'num_seeds' => $targetTorrent['num_seeds'] ?? 0,
                        'num_complete' => $targetTorrent['num_complete'] ?? 0,
                        'num_leechs' => $targetTorrent['num_leechs'] ?? 0,
                        'num_incomplete' => $targetTorrent['num_incomplete'] ?? 0,
                        'tracker' => $targetTorrent['tracker'] ?? '',
                        'finished' => ($targetTorrent['state'] ?? '') === 'completed' || ($targetTorrent['state'] ?? '') === 'stalled_uploading',
                        'stalled' => strpos($targetTorrent['state'] ?? '', 'stalled') === 0,
                        'force_start' => $targetTorrent['force_start'] ?? false,
                        'sequential_download' => $targetTorrent['seq_dl'] ?? false,
                        'first_last_piece_prio' => $targetTorrent['f_l_piece_prio'] ?? false,
                    ],
                    'properties' => [
                        'save_path' => $properties['save_path'] ?? '',
                        'creation_date' => $properties['creation_date'] ?? 0,
                        'comment' => $properties['comment'] ?? '',
                        'total_wasted' => $properties['total_wasted'] ?? 0,
                        'formatted_total_wasted' => $this->formatBytes($properties['total_wasted'] ?? 0),
                        'total_uploaded' => $properties['total_uploaded'] ?? 0,
                        'formatted_total_uploaded' => $this->formatBytes($properties['total_uploaded'] ?? 0),
                        'total_downloaded' => $properties['total_downloaded'] ?? 0,
                        'formatted_total_downloaded' => $this->formatBytes($properties['total_downloaded'] ?? 0),
                        'up_limit' => $properties['up_limit'] ?? 0,
                        'formatted_up_limit' => ($properties['up_limit'] ?? 0) > 0 ? $this->formatBytes($properties['up_limit'] ?? 0).'/s' : '无限制',
                        'dl_limit' => $properties['dl_limit'] ?? 0,
                        'formatted_dl_limit' => ($properties['dl_limit'] ?? 0) > 0 ? $this->formatBytes($properties['dl_limit'] ?? 0).'/s' : '无限制',
                        'time_elapsed' => $properties['time_elapsed'] ?? 0,
                        'formatted_time_elapsed' => $this->formatDuration($properties['time_elapsed'] ?? 0),
                        'seeding_time' => $properties['seeding_time'] ?? 0,
                        'formatted_seeding_time' => $this->formatDuration($properties['seeding_time'] ?? 0),
                        'nb_connections' => $properties['nb_connections'] ?? 0,
                        'nb_connections_limit' => $properties['nb_connections_limit'] ?? 0,
                        'share_ratio' => $properties['share_ratio'] ?? 0,
                        'addition_date' => $properties['addition_date'] ?? 0,
                        'completion_date' => $properties['completion_date'] ?? 0,
                        'creator' => $properties['creator'] ?? '',
                        'torrent_hash' => $properties['torrent_hash'] ?? '',
                        'piece_size' => $properties['piece_size'] ?? 0,
                        'formatted_piece_size' => $this->formatBytes($properties['piece_size'] ?? 0),
                        'num_pieces' => $properties['num_pieces'] ?? 0,
                        'piece_count' => $properties['piece_count'] ?? 0,
                    ],
                    'files' => $files,
                    'trackers' => $trackers,
                    'connection_info' => [
                        'server_url' => QbittorrentService::getInstance()->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => QbittorrentService::getInstance()->getServerConfig()['username'] ?? 'unknown',
                        'connected' => QbittorrentService::getInstance()->getServerConfig()['connected'] ?? false,
                    ],
                ];
            });

            return Response::text(json_encode($torrentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } catch (\Exception $e) {
            Log::error('获取种子属性失败', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::error('获取种子属性失败: '.$e->getMessage());
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
     * 格式化持续时间为可读格式
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0秒';
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.'天';
        }
        if ($hours > 0) {
            $parts[] = $hours.'小时';
        }
        if ($minutes > 0) {
            $parts[] = $minutes.'分钟';
        }
        if ($remainingSeconds > 0 || empty($parts)) {
            $parts[] = $remainingSeconds.'秒';
        }

        return implode(' ', $parts);
    }
}
