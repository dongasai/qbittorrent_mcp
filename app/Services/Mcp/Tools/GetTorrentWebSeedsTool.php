<?php

namespace App\Services\Mcp\Tools;

use App\Services\QbittorrentService;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Support\Facades\Log;

/**
 * 获取种子Web Seeds信息工具
 *
 * 专门用于获取指定种子的Web Seed信息，包括：
 * - Web Seed URL列表
 * - Web Seed状态信息
 */
class GetTorrentWebSeedsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取指定种子的Web Seed信息，包括URL列表和状态';

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
     * 获取torrent web seeds
     *
     * 获取指定种子的Web Seed信息：
     * - 返回Web Seed URL列表
     * - 显示每个Web Seed的状态
     */
    private function execute(string $hash): array
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            return $qbittorrent->executeWithAuth(function ($client) use ($hash, $qbittorrent) {
                // 使用通用的 GET 方法来获取 web seeds 信息
                $response = $client->torrents()->get('/webSeeds', ['hash' => $hash]);

                if (!$response->isSuccess()) {
                    throw new \Exception('无法获取种子Web Seeds: ' . implode(', ', $response->getErrors()));
                }

                $webSeedsData = $response->getData() ?? [];
                $result = [];

                // 解析响应数据
                if (is_array($webSeedsData)) {
                    foreach ($webSeedsData as $webSeed) {
                        $result[] = [
                            'url' => $webSeed['url'] ?? '',
                        ];
                    }
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
}