<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use PhpMcp\Server\Attributes\McpTool;
use Illuminate\Support\Facades\Log;

/**
 * qBittorrent 种子详细信息工具
 *
 * 提供种子的详细查询功能，包括trackers、web seeds、内容、片段信息等
 */
class TorrentDetailTool
{
    /**
     * 获取torrent trackers
     *
     * 获取指定种子的所有Tracker信息：
     * - 返回Tracker URL、状态、连接数等
     * - 显示每个Tracker的种子和下载数
     * - 包含Tracker的工作状态信息
     */
    #[McpTool(name: 'get_torrent_trackers')]
    public function getTorrentTrackers(string $hash): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash) {
                $request = \PhpQbittorrent\Request\Torrent\GetTorrentTrackersRequest::create($hash);
                $response = $client->torrents()->getTorrentTrackers($request);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子Trackers: ' . implode(', ', $response->getErrors()));
                }

                $trackers = $response->getTrackers();
                $result = [];

                foreach ($trackers as $tracker) {
                    $result[] = [
                        'url' => $tracker->getUrl(),
                        'status' => $tracker->getStatus(),
                        'status_description' => $this->getTrackerStatusDescription($tracker->getStatus()),
                        'message' => $tracker->getMessage(),
                        'peers' => $tracker->getPeers(),
                        'seeds' => $tracker->getSeeds(),
                        'limit' => $tracker->getLimit(),
                        'downloaded' => $tracker->getDownloaded(),
                        'tier' => $tracker->getTier() ?? 0,
                    ];
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
     * 获取torrent web seeds
     *
     * 获取指定种子的Web Seed信息：
     * - 返回Web Seed URL列表
     * - 显示每个Web Seed的状态
     */
    #[McpTool(name: 'get_torrent_web_seeds')]
    public function getTorrentWebSeeds(string $hash): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash) {
                $request = \PhpQbittorrent\Request\Torrent\GetTorrentWebSeedsRequest::create($hash);
                $response = $client->torrents()->getTorrentWebSeeds($request);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子Web Seeds: ' . implode(', ', $response->getErrors()));
                }

                $webSeeds = $response->getWebSeeds();
                $result = [];

                foreach ($webSeeds as $webSeed) {
                    $result[] = [
                        'url' => $webSeed->getUrl(),
                    ];
                }

                return [
                    'success' => true,
                    'torrent_hash' => $hash,
                    'web_seeds' => $result,
                    'web_seed_count' => count($result),
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取种子Web Seeds失败', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取种子Web Seeds失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取种子内容
     *
     * 获取指定种子的文件列表信息：
     * - 返回种子包含的所有文件
     * - 显示文件大小、进度、优先级等
     * - 支持大种子的分页查询
     */
    #[McpTool(name: 'get_torrent_contents')]
    public function getTorrentContents(string $hash, ?int $limit = null, ?int $offset = 0): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash, $limit, $offset) {
                $request = \PhpQbittorrent\Request\Torrent\GetTorrentFilesRequest::create($hash);
                $response = $client->torrents()->getTorrentFiles($request);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子内容: ' . implode(', ', $response->getErrors()));
                }

                $files = $response->getFiles();
                $result = [];
                $totalSize = 0;
                $totalDownloaded = 0;

                foreach ($files as $file) {
                    $fileData = [
                        'index' => $file->getIndex(),
                        'name' => $file->getName(),
                        'size' => $file->getSize(),
                        'formatted_size' => $this->formatBytes($file->getSize()),
                        'progress' => $file->getProgress(),
                        'progress_percentage' => round($file->getProgress() * 100, 2) . '%',
                        'priority' => $file->getPriority(),
                        'priority_description' => $this->getPriorityDescription($file->getPriority()),
                        'is_seed' => $file->isSeed(),
                        'piece_range' => $file->getPieceRange(),
                        'availability' => $file->getAvailability(),
                        'path' => dirname($file->getName()),
                        'filename' => basename($file->getName()),
                    ];

                    $result[] = $fileData;
                    $totalSize += $file->getSize();
                    $totalDownloaded += $file->getSize() * $file->getProgress();
                }

                // 应用分页
                if ($limit !== null) {
                    $result = array_slice($result, $offset, $limit);
                }

                return [
                    'success' => true,
                    'torrent_hash' => $hash,
                    'files' => $result,
                    'file_count' => count($files),
                    'total_size' => $totalSize,
                    'formatted_total_size' => $this->formatBytes($totalSize),
                    'total_downloaded' => $totalDownloaded,
                    'formatted_total_downloaded' => $this->formatBytes($totalDownloaded),
                    'overall_progress' => $totalSize > 0 ? round(($totalDownloaded / $totalSize) * 100, 2) . '%' : '0%',
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'returned_count' => count($result)
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取种子内容失败', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取种子内容失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取torrent片段状态
     *
     * 获取指定种子的片段状态信息：
     * - 显示每个片段的下载状态
     * - 支持范围查询以处理大型种子
     */
    #[McpTool(name: 'get_torrent_piece_states')]
    public function getTorrentPieceStates(string $hash, ?int $limit = null, ?int $offset = 0): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash, $limit, $offset) {
                $request = \PhpQbittorrent\Request\Torrent\GetTorrentPieceStatesRequest::create($hash);
                $response = $client->torrents()->getTorrentPieceStates($request);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子片段状态: ' . implode(', ', $response->getErrors()));
                }

                $pieceStates = $response->getPieceStates();
                $pieces = [];

                foreach ($pieceStates as $index => $state) {
                    $pieces[] = [
                        'index' => $index,
                        'state' => $state,
                        'downloaded' => $state === true || $state === 1,
                    ];
                }

                // 应用分页
                $totalPieces = count($pieces);
                if ($limit !== null) {
                    $pieces = array_slice($pieces, $offset, $limit);
                }

                $downloadedCount = count(array_filter($pieces, fn($p) => $p['downloaded']));

                return [
                    'success' => true,
                    'torrent_hash' => $hash,
                    'pieces' => $pieces,
                    'total_pieces' => $totalPieces,
                    'downloaded_pieces' => $downloadedCount,
                    'downloaded_percentage' => round(($downloadedCount / count($pieces)) * 100, 2) . '%',
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'returned_count' => count($pieces)
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取种子片段状态失败', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取种子片段状态失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取torrent片段hash
     *
     * 获取指定种子的片段哈希值：
     * - 返回每个片段的SHA1哈希
     * - 用于完整性验证
     */
    #[McpTool(name: 'get_torrent_piece_hashes')]
    public function getTorrentPieceHashes(string $hash, ?int $limit = null, ?int $offset = 0): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash, $limit, $offset) {
                $request = \PhpQbittorrent\Request\Torrent\GetTorrentPieceHashesRequest::create($hash);
                $response = $client->torrents()->getTorrentPieceHashes($request);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子片段哈希: ' . implode(', ', $response->getErrors()));
                }

                $pieceHashes = $response->getPieceHashes();
                $pieces = [];

                foreach ($pieceHashes as $index => $hash) {
                    $pieces[] = [
                        'index' => $index,
                        'hash' => $hash,
                    ];
                }

                // 应用分页
                $totalPieces = count($pieces);
                if ($limit !== null) {
                    $pieces = array_slice($pieces, $offset, $limit);
                }

                return [
                    'success' => true,
                    'torrent_hash' => $hash,
                    'pieces' => $pieces,
                    'total_pieces' => $totalPieces,
                    'hash_algorithm' => 'SHA1',
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'returned_count' => count($pieces)
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取种子片段哈希失败', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取种子片段哈希失败: ' . $e->getMessage()
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

    /**
     * 获取文件优先级描述
     */
    private function getPriorityDescription(int $priority): string
    {
        return match($priority) {
            0 => '不下载',
            1 => '普通优先级',
            6 => '高优先级',
            7 => '最高优先级',
            default => '优先级 ' . $priority
        };
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