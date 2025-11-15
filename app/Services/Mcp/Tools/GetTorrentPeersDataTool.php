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
 * qBittorrent 获取种子节点数据工具
 *
 * 获取指定种子的 Peers 连接信息，包括：
 * - Peer 列表和详细信息
 * - 连接状态和标志位
 * - 客户端信息和来源
 * - 传输统计和进度
 * - 汇总统计数据
 */
#[IsReadOnly]
#[IsIdempotent]
class GetTorrentPeersDataTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取指定种子的 Peers 连接信息，包括节点列表、连接状态、客户端信息和传输统计';

    /**
     * The tool's name.
     */
    protected string $name = 'get_torrent_peers_data';

    /**
     * The tool's title.
     */
    protected string $title = 'Get Torrent Peers Data';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'hash' => $schema->string('种子的40位哈希值'),
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

            $peersData = $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent, $hash) {
                // 获取种子列表以验证种子存在
                $torrentsRequest = \PhpQbittorrent\Request\Torrent\GetTorrentsRequest::create();
                $torrentsRequest->setHashes([(string) $hash]);
                $torrentsResponse = $client->torrents()->getTorrents($torrentsRequest);

                if (! $torrentsResponse->isSuccess() || $torrentsResponse->getTorrents()->isEmpty()) {
                    throw new \Exception("种子不存在或无法访问: {$hash}");
                }

                $torrent = $torrentsResponse->getTorrents()->first();

                // 获取 Peers 数据
                $peersRequest = \PhpQbittorrent\Request\Torrent\GetTorrentPeersRequest::create($hash);
                $peersResponse = $client->torrents()->getTorrentPeers($peersRequest);

                if (! $peersResponse->isSuccess()) {
                    throw new \Exception('无法获取 Peers 数据: '.implode(', ', $peersResponse->getErrors()));
                }

                $peers = $peersResponse->getPeers();

                // 处理 Peers 数据
                $peerData = [];
                foreach ($peers as $peer) {
                    $flags = $peer['flags'] ?? 0;
                    $peerData[] = [
                        'ip' => $peer['ip'] ?? '',
                        'port' => $peer['port'] ?? 0,
                        'client' => $peer['client'] ?? '',
                        'progress' => $peer['progress'] ?? 0,
                        'progress_percentage' => round(($peer['progress'] ?? 0) * 100, 2).'%',
                        'download_speed' => $peer['dl_speed'] ?? 0,
                        'upload_speed' => $peer['up_speed'] ?? 0,
                        'formatted_download_speed' => $this->formatBytes($peer['dl_speed'] ?? 0).'/s',
                        'formatted_upload_speed' => $this->formatBytes($peer['up_speed'] ?? 0).'/s',
                        'downloaded' => $peer['downloaded'] ?? 0,
                        'uploaded' => $peer['uploaded'] ?? 0,
                        'formatted_downloaded' => $this->formatBytes($peer['downloaded'] ?? 0),
                        'formatted_uploaded' => $this->formatBytes($peer['uploaded'] ?? 0),
                        'connection_status' => $peer['connection'] ?? '',
                        'flags' => [
                            'interesting' => ($flags & 0x02) !== 0,
                            'choked' => ($flags & 0x01) !== 0,
                            'remote_choked' => ($flags & 0x10) !== 0,
                            'remote_interested' => ($flags & 0x08) !== 0,
                        ],
                        'source' => $peer['source'] ?? '',
                        'relevance' => $peer['relevance'] ?? 0,
                    ];
                }

                return [
                    'torrent_info' => [
                        'hash' => $hash,
                        'name' => $torrent->getName() ?? '',
                        'size' => $torrent->getSize() ?? 0,
                        'formatted_size' => $this->formatBytes($torrent->getSize() ?? 0),
                        'state' => $torrent->getState() ?? '',
                    ],
                    'peers_data' => [
                        'total_peers' => count($peerData),
                        'active_peers' => count(array_filter($peerData, fn ($p) => ! ($p['flags']['choked']))),
                        'completed_peers' => count(array_filter($peerData, fn ($p) => $p['progress'] >= 1000)),
                        'peers' => $peerData,
                    ],
                    'statistics' => [
                        'total_download_sum' => array_sum(array_column($peerData, 'download_speed')),
                        'total_upload_sum' => array_sum(array_column($peerData, 'upload_speed')),
                        'formatted_download_sum' => $this->formatBytes(array_sum(array_column($peerData, 'download_speed'))).'/s',
                        'formatted_upload_sum' => $this->formatBytes(array_sum(array_column($peerData, 'upload_speed'))).'/s',
                        'total_downloaded' => array_sum(array_column($peerData, 'downloaded')),
                        'total_uploaded' => array_sum(array_column($peerData, 'uploaded')),
                        'formatted_total_downloaded' => $this->formatBytes(array_sum(array_column($peerData, 'downloaded'))),
                        'formatted_total_uploaded' => $this->formatBytes(array_sum(array_column($peerData, 'uploaded'))),
                        'average_progress' => count($peerData) > 0 ? array_sum(array_column($peerData, 'progress')) / count($peerData) : 0,
                        'average_progress_percentage' => count($peerData) > 0 ? round(array_sum(array_column($peerData, 'progress')) / count($peerData) * 100, 2).'%' : '0%',
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false,
                    ],
                ];
            });

            return Response::text(json_encode($peersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } catch (\Exception $e) {
            Log::error('获取种子节点数据失败', [
                'error' => $e->getMessage(),
                'torrent_hash' => $hash ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::error('获取种子节点数据失败: '.$e->getMessage());
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
