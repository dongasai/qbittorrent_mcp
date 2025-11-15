<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Illuminate\Support\Facades\Log;
use PhpMcp\Server\Attributes\McpTool;

/**
 * qBittorrent 种子管理工具
 *
 * 提供种子列表和属性查询功能
 */
class TorrentManagementTool
{
    /**
     * 列出种子
     *
     * 获取 qBittorrent 中的种子列表，支持多种过滤和排序选项：
     *
     * 支持的过滤选项 (filter)：
     * - 'all' - 所有种子 (默认)
     * - 'downloading' - 下载中的种子
     * - 'completed' - 已完成的种子
     * - 'paused' - 暂停的种子
     * - 'active' - 活动的种子
     * - 'inactive' - 非活动的种子
     * - 'resumed' - 恢复的种子
     * - 'stalled' - 停滞的种子
     * - 'stalled_uploading' - 上传停滞的种子
     * - 'stalled_downloading' - 下载停滞的种子
     *
     * 支持的排序字段 (sort)：
     * - 'hash', 'name', 'size', 'progress', 'dl_speed', 'up_speed'
     * - 'priority', 'num_seeds', 'num_leechs', 'ratio', 'eta'
     * - 'state', 'category', 'tags', 'save_path', 'added_on'
     *
     * 其他参数：
     * - category - 按分类名称过滤
     * - tag - 按标签名称过滤
     * - reverse - 是否反向排序 (true/false)
     * - limit - 限制返回数量 (最大1000)
     * - offset - 偏移量，用于分页
     *
     * 返回详细的种子信息，包括进度、速度、连接数等
     */
    #[McpTool(name: 'list_torrents')]
    public function listTorrents(
        ?string $filter = null,
        ?string $category = null,
        ?string $tag = null,
        ?string $sort = null,
        ?bool $reverse = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($filter, $category, $tag, $sort, $reverse, $limit, $offset) {
                // 构建请求
                $request = \PhpQbittorrent\Request\Torrent\GetTorrentsRequest::create();

                // 应用过滤条件
                if ($filter) {
                    $request->setFilter(\PhpQbittorrent\Enum\TorrentFilter::fromString($filter));
                }
                if ($category) {
                    $request->setCategory($category);
                }
                if ($tag) {
                    $request->setTag($tag);
                }
                if ($sort) {
                    $request->setSort($sort);
                }
                if ($reverse !== null) {
                    $request->setReverse($reverse);
                }
                if ($limit !== null) {
                    $request->setLimit((int)$limit);
                }
                if ($offset !== null) {
                    $request->setOffset((int)$offset);
                }

                $response = $client->torrents()->getTorrents($request);

                if (! $response->isSuccess()) {
                    throw new \Exception('无法获取种子列表: '.implode(', ', $response->getErrors()));
                }

                $torrents = $response->getTorrents();
                $result = [];

                foreach ($torrents as $torrent) {
                    $result[] = [
                        'hash' => $torrent->getHash(),
                        'name' => $torrent->getName(),
                        'size' => $torrent->getSize(),
                        'progress' => $torrent->getProgress(),
                        'progress_percentage' => round($torrent->getProgress() * 100, 2).'%',
                        'download_speed' => $torrent->getDownloadSpeed(),
                        'upload_speed' => $torrent->getUploadSpeed(),
                        'downloaded' => $torrent->getDownloaded(),
                        'uploaded' => $torrent->getUploaded(),
                        'ratio' => $torrent->getRatio(),
                        'state' => $torrent->getState(),
                        'priority' => $torrent->getPriority(),
                        'num_seeds' => $torrent->getSeedCount(),
                        'num_complete' => $torrent->getTotalSeedCount(),
                        'num_leechs' => $torrent->getLeechCount(),
                        'num_incomplete' => $torrent->getTotalLeechCount(),
                        'category' => $torrent->getCategory(),
                        'tags' => $torrent->getTags(),
                        'save_path' => $torrent->getSavePath(),
                        'added_time' => $torrent->getAddedOn(),
                        'completion_time' => $torrent->getCompletionOn(),
                        'tracker' => $torrent->getTracker(),
                        'dl_limit' => $torrent->getDownloadLimit(),
                        'up_limit' => $torrent->getUploadLimit(),
                        'downloaded_session' => $torrent->getSessionDownloaded(),
                        'uploaded_session' => $torrent->getSessionUploaded(),
                        'amount_left' => $torrent->getAmountLeft(),
                        'seeding_time' => $torrent->getTimeActive(), // 使用time_active作为替代
                        'finished' => $torrent->isCompleted(),
                        'stalled' => $torrent->isStalled(),
                        'force_start' => $torrent->isForceStarted(),
                        'sequential_download' => $torrent->isSequentialDownload(),
                        'first_last_piece_prio' => $torrent->isFirstLastPiecePriority(),
                    ];
                }

                return [
                    'success' => true,
                    'torrents' => $result,
                    'total_count' => count($result),
                    'filters_applied' => [
                        'filter' => $filter,
                        'category' => $category,
                        'tag' => $tag,
                        'sort' => $sort,
                        'reverse' => $reverse,
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                    'connection_info' => [
                        'server_url' => 'qBittorrent',
                        'username' => 'connected',
                        'connected' => true,
                    ],
                ];
            });
        } catch (\Exception $e) {
            Log::error('列出种子失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '列出种子失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 获取种子详细属性
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
    #[McpTool(name: 'get_torrent_properties')]
    public function getTorrentProperties(string $hash): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash) {
                // 获取种子列表以找到指定种子
                $listRequest = \PhpQbittorrent\Request\Torrent\GetTorrentsRequest::create();
                $listResponse = $client->torrents()->getTorrents($listRequest);

                if (! $listResponse->isSuccess()) {
                    throw new \Exception('无法获取种子列表: '.implode(', ', $listResponse->getErrors()));
                }

                $torrents = $listResponse->getTorrents();
                $targetTorrent = null;

                foreach ($torrents as $torrent) {
                    if ($torrent->getHash() === $hash) {
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
                $torrentFiles = $client->torrents()->getTorrentFiles($hash);
                $files = [];
                foreach ($torrentFiles as $file) {
                    $files[] = [
                        'index' => $file['index'] ?? 0,
                        'name' => $file['name'] ?? '',
                        'size' => $file['size'] ?? 0,
                        'progress' => $file['progress'] ?? 0,
                        'priority' => $file['priority'] ?? 0,
                        'is_seed' => $file['is_seed'] ?? false,
                        'piece_range' => $file['piece_range'] ?? '',
                        'availability' => $file['availability'] ?? 0,
                    ];
                }

                // 注意：当前版本的API中没有获取tracker的方法，暂时留空
                $trackers = [];

                return [
                    'success' => true,
                    'torrent_info' => [
                        'hash' => $targetTorrent->getHash(),
                        'name' => $targetTorrent->getName(),
                        'size' => $targetTorrent->getSize(),
                        'formatted_size' => $this->formatBytes($targetTorrent->getSize()),
                        'progress' => $targetTorrent->getProgress(),
                        'progress_percentage' => round($targetTorrent->getProgress() * 100, 2).'%',
                        'state' => $targetTorrent->getState(),
                        'category' => $targetTorrent->getCategory(),
                        'tags' => $targetTorrent->getTags(),
                        'save_path' => $targetTorrent->getSavePath(),
                        'added_time' => $targetTorrent->getAddedOn(),
                        'completion_time' => $targetTorrent->getCompletionOn(),
                        'download_speed' => $targetTorrent->getDownloadSpeed(),
                        'upload_speed' => $targetTorrent->getUploadSpeed(),
                        'downloaded' => $targetTorrent->getDownloaded(),
                        'uploaded' => $targetTorrent->getUploaded(),
                        'ratio' => $targetTorrent->getRatio(),
                        'dl_limit' => $targetTorrent->getDownloadLimit(),
                        'up_limit' => $targetTorrent->getUploadLimit(),
                    ],
                    'properties' => [
                        'save_path' => $properties->getSavePath(),
                        'creation_date' => $properties->getCreationDate(),
                        'comment' => $properties->getComment(),
                        'total_wasted' => $properties->getTotalWasted(),
                        'total_uploaded' => $properties->getTotalUploaded(),
                        'total_downloaded' => $properties->getTotalDownloaded(),
                        'up_limit' => $properties->getUpLimit(),
                        'dl_limit' => $properties->getDlLimit(),
                        'time_elapsed' => $properties->getTimeElapsed(),
                        'seeding_time' => $properties->getSeedingTime(),
                        'nb_connections' => $properties->getNbConnections(),
                        'nb_connections_limit' => $properties->getNbConnectionsLimit(),
                        'share_ratio' => $properties->getShareRatio(),
                        'addition_date' => $properties->getAdditionDate(),
                        'completion_date' => $properties->getCompletionDate(),
                        'creator' => $properties->getCreator(),
                        'torrent_hash' => $properties->getTorrentHash(),
                        'piece_size' => $properties->getPieceSize(),
                        'num_pieces' => $properties->getNumPieces(),
                        'piece_count' => $properties->getPieceCount(),
                    ],
                    'files' => $files,
                    'trackers' => $trackers,
                    'connection_info' => [
                        'server_url' => 'qBittorrent',
                        'username' => 'connected',
                        'connected' => true,
                    ],
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取种子属性失败', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取种子属性失败: '.$e->getMessage(),
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

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
