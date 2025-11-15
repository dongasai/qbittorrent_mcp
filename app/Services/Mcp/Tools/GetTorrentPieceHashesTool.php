<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Support\Facades\Log;

/**
 * 获取种子片段哈希工具
 *
 * 专门用于获取指定种子的片段哈希值，包括：
 * - 每个片段的SHA1哈希
 * - 用于完整性验证
 */
class GetTorrentPieceHashesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取指定种子的片段哈希值，用于完整性验证';

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
     * 获取torrent片段hash
     *
     * 获取指定种子的片段哈希值：
     * - 返回每个片段的SHA1哈希
     * - 用于完整性验证
     */
    private function execute(string $hash, ?int $limit = null, ?int $offset = 0): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash, $limit, $offset, $qbittorrent) {
                // 使用通用的 GET 方法来获取 piece hashes 信息
                $response = $client->torrents()->get('/pieceHashes', ['hash' => $hash]);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子片段哈希: ' . implode(', ', $response->getErrors()));
                }

                $pieceHashesData = $response->getData();
                $pieces = [];

                // 解析响应数据，通常返回的是哈希值的数组
                if (is_string($pieceHashesData)) {
                    // 如果返回的是管道分隔的字符串
                    $hashes = explode('|', trim($pieceHashesData));
                    foreach ($hashes as $index => $hashValue) {
                        if (!empty($hashValue)) {
                            $pieces[] = [
                                'index' => $index,
                                'hash' => $hashValue,
                            ];
                        }
                    }
                } elseif (is_array($pieceHashesData)) {
                    foreach ($pieceHashesData as $index => $hashValue) {
                        $pieces[] = [
                            'index' => $index,
                            'hash' => $hashValue,
                        ];
                    }
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
}