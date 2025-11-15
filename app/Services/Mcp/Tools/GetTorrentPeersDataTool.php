<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use PhpMcp\Server\Attributes\McpTool;
use Illuminate\Support\Facades\Log;

/**
 * qBittorrent 获取种子节点数据工具
 *
 * 获取指定种子的 Peers 连接信息
 */
class GetTorrentPeersDataTool
{
    /**
     * 获取种子节点数据
     *
     * 获取指定种子的 Peers 信息：
     * - Peer 列表
     * - 连接状态
     * - 客户端信息
     * - 传输统计
     */
    #[McpTool(name: 'get_torrent_peers_data')]
    public function getTorrentPeersData(string $hash): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent, $hash) {
                // 获取种子列表以验证种子存在
                $torrentsRequest = \PhpQbittorrent\Request\Torrent\GetTorrentsRequest::create()
                    ->withHashes($hash);
                $torrentsResponse = $client->torrents()->getTorrents($torrentsRequest);

                if (!$torrentsResponse->isSuccess() || empty($torrentsResponse->getTorrents())) {
                    throw new \Exception("种子不存在或无法访问: {$hash}");
                }

                $torrent = $torrentsResponse->getTorrents()[0];

                // 获取 Peers 数据
                $peersRequest = \PhpQbittorrent\Request\Torrent\GetTorrentPeersRequest::create($hash);
                $peersResponse = $client->torrents()->getTorrentPeers($peersRequest);

                if (!$peersResponse->isSuccess()) {
                    throw new \Exception('无法获取 Peers 数据: ' . implode(', ', $peersResponse->getErrors()));
                }

                $peers = $peersResponse->getPeers();

                // 处理 Peers 数据
                $peerData = [];
                foreach ($peers as $peer) {
                    $peerData[] = [
                        'ip' => $peer['ip'] ?? '',
                        'port' => $peer['port'] ?? 0,
                        'client' => $peer['client'] ?? '',
                        'progress' => $peer['progress'] ?? 0,
                        'download_speed' => $peer['dl_speed'] ?? 0,
                        'upload_speed' => $peer['up_speed'] ?? 0,
                        'downloaded' => $peer['downloaded'] ?? 0,
                        'uploaded' => $peer['uploaded'] ?? 0,
                        'connection_status' => $peer['connection'] ?? '',
                        'flags' => [
                            'interesting' => ($peer['flags'] & 0x02) !== 0,
                            'choked' => ($peer['flags'] & 0x01) !== 0,
                            'remote_choked' => ($peer['flags'] & 0x10) !== 0,
                            'remote_interested' => ($peer['flags'] & 0x08) !== 0,
                        ],
                        'source' => $peer['source'] ?? '',
                        'relevance' => $peer['relevance'] ?? 0,
                    ];
                }

                return [
                    'success' => true,
                    'torrent_info' => [
                        'hash' => $hash,
                        'name' => $torrent->getName() ?? '',
                        'size' => $torrent->getSize() ?? 0,
                        'state' => $torrent->getState() ?? '',
                    ],
                    'peers_data' => [
                        'total_peers' => count($peerData),
                        'active_peers' => count(array_filter($peerData, fn($p) => ($p['flags'] & 0x01) === 0)),
                        'completed_peers' => count(array_filter($peerData, fn($p) => $p['progress'] >= 1000)),
                        'peers' => $peerData,
                    ],
                    'statistics' => [
                        'total_download_sum' => array_sum(array_column($peerData, 'download_speed')),
                        'total_upload_sum' => array_sum(array_column($peerData, 'upload_speed')),
                        'total_downloaded' => array_sum(array_column($peerData, 'downloaded')),
                        'total_uploaded' => array_sum(array_column($peerData, 'uploaded')),
                        'average_progress' => count($peerData) > 0 ? array_sum(array_column($peerData, 'progress')) / count($peerData) : 0,
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error('获取种子节点数据失败', [
                'error' => $e->getMessage(),
                'torrent_hash' => $hash ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => '获取种子节点数据失败: ' . $e->getMessage()
            ];
        }
    }
}