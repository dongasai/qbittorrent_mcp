<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Support\Facades\Log;

/**
 * 获取种子片段状态工具
 *
 * 专门用于获取指定种子的片段状态信息，包括：
 * - 每个片段的下载状态
 * - 支持范围查询以处理大型种子
 */
class GetTorrentPieceStatesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取指定种子的片段状态信息，支持分页查询';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $hash = $request->get('hash');
        $limit = $request->get('limit');
        $offset = $request->get('offset', 0);

        $result = $this->execute($hash, $limit, $offset);

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
            'limit' => $schema->integer()
                ->description('返回片段数量的限制（可选）'),
            'offset' => $schema->integer()
                ->description('偏移量，默认为0（可选）'),
        ];
    }

    /**
     * 获取torrent片段状态
     *
     * 获取指定种子的片段状态信息：
     * - 显示每个片段的下载状态
     * - 支持范围查询以处理大型种子
     */
    private function execute(string $hash, ?int $limit = null, ?int $offset = 0): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash, $limit, $offset, $qbittorrent) {
                // 使用通用的 GET 方法来获取 piece states 信息
                $response = $client->torrents()->get('/pieceStates', ['hash' => $hash]);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子片段状态: ' . implode(', ', $response->getErrors()));
                }

                $pieceStatesData = $response->getData();
                $pieces = [];

                // 解析响应数据，通常返回的是字符串形式的0和1序列
                if (is_string($pieceStatesData)) {
                    $states = str_split($pieceStatesData);
                    foreach ($states as $index => $state) {
                        $pieces[] = [
                            'index' => $index,
                            'state' => (int)$state,
                            'downloaded' => $state === '1',
                        ];
                    }
                } elseif (is_array($pieceStatesData)) {
                    foreach ($pieceStatesData as $index => $state) {
                        $pieces[] = [
                            'index' => $index,
                            'state' => is_bool($state) ? ($state ? 1 : 0) : $state,
                            'downloaded' => $state === true || $state === 1,
                        ];
                    }
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
                    'downloaded_percentage' => $totalPieces > 0 ? round(($downloadedCount / $totalPieces) * 100, 2) . '%' : '0%',
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
}