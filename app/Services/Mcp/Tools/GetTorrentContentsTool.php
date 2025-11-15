<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * 获取种子内容工具
 *
 * 专门用于获取指定种子的文件列表信息，包括：
 * - 种子包含的所有文件
 * - 文件大小、进度、优先级等
 * - 支持大种子的分页查询
 */
class GetTorrentContentsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取指定种子的文件列表信息，包括大小、进度、优先级等';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $hash = $request->get('hash');
        $limit = $request->get('limit');
        $offset = $request->get('offset', 0);

        $result = $this->execute($hash, $limit, $offset);

        if (! $result['success']) {
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
            'limit' => $schema->integer()
                ->description('返回文件数量的限制（可选）'),
            'offset' => $schema->integer()
                ->description('偏移量，默认为0（可选）'),
        ];
    }

    /**
     * 获取种子内容
     *
     * 获取指定种子的文件列表信息：
     * - 返回种子包含的所有文件
     * - 显示文件大小、进度、优先级等
     * - 支持大种子的分页查询
     */
    private function execute(string $hash, ?int $limit = null, ?int $offset = 0): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function (\PhpQbittorrent\Client $client) use ($hash, $limit, $offset, $qbittorrent) {
                // 使用现有的 getTorrentFiles 方法
                $files = $client->torrents()->getTorrentFiles($hash);
                $result = [];
                $totalSize = 0;
                $totalDownloaded = 0;

                foreach ($files as $file) {
                    $fileData = [
                        'index' => $file['index'] ?? 0,
                        'name' => $file['name'] ?? '',
                        'size' => $file['size'] ?? 0,
                        'formatted_size' => $this->formatBytes($file['size'] ?? 0),
                        'progress' => ($file['progress'] ?? 0) / 100, // API 返回的是百分比，转换为小数
                        'progress_percentage' => round($file['progress'] ?? 0, 2).'%',
                        'priority' => $file['priority'] ?? 1,
                        'priority_description' => $this->getPriorityDescription($file['priority'] ?? 1),
                        'is_seed' => ($file['progress'] ?? 0) >= 100,
                        'piece_range' => $file['piece_range'] ?? '',
                        'availability' => $file['availability'] ?? 0,
                        'path' => dirname($file['name'] ?? ''),
                        'filename' => basename($file['name'] ?? ''),
                    ];

                    $result[] = $fileData;
                    $totalSize += $file['size'] ?? 0;
                    $totalDownloaded += ($file['size'] ?? 0) * (($file['progress'] ?? 0) / 100);
                }

                // 应用分页
                $totalFiles = count($files);
                if ($limit !== null) {
                    $result = array_slice($result, $offset, $limit);
                }

                return [
                    'success' => true,
                    'torrent_hash' => $hash,
                    'files' => $result,
                    'file_count' => $totalFiles,
                    'total_size' => $totalSize,
                    'formatted_total_size' => $this->formatBytes($totalSize),
                    'total_downloaded' => $totalDownloaded,
                    'formatted_total_downloaded' => $this->formatBytes($totalDownloaded),
                    'overall_progress' => $totalSize > 0 ? round(($totalDownloaded / $totalSize) * 100, 2).'%' : '0%',
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'returned_count' => count($result),
                    ],
                    'connection_info' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false,
                    ],
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
                'error' => '获取种子内容失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 获取文件优先级描述
     */
    private function getPriorityDescription(int $priority): string
    {
        return match ($priority) {
            0 => '不下载',
            1 => '普通优先级',
            6 => '高优先级',
            7 => '最高优先级',
            default => '优先级 '.$priority
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

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
