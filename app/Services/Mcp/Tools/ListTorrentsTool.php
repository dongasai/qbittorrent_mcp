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
 * qBittorrent 种子列表工具
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
#[IsReadOnly]
#[IsIdempotent]
class ListTorrentsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取 qBittorrent 中的种子列表，支持多种过滤和排序选项，返回详细的种子信息';

    /**
     * The tool's name.
     */
    protected string $name = 'list_torrents';

    /**
     * The tool's title.
     */
    protected string $title = 'List Torrents';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string('过滤条件：all, downloading, completed, paused, active, inactive, resumed, stalled, stalled_uploading, stalled_downloading')
                ->description('种子状态过滤器'),
            'category' => $schema->string('分类名称')
                ->description('按分类名称过滤种子'),
            'tag' => $schema->string('标签名称')
                ->description('按标签名称过滤种子'),
            'sort' => $schema->string('排序字段')
                ->description('排序字段：hash, name, size, progress, dl_speed, up_speed, priority, num_seeds, num_leechs, ratio, eta, state, category, tags, save_path, added_on'),
            'reverse' => $schema->boolean('是否反向排序')
                ->description('设置是否为反向排序'),
            'limit' => $schema->integer('限制数量')
                ->description('限制返回数量，最大1000')
                ->max(1000)
                ->min(1),
            'offset' => $schema->integer('偏移量')
                ->description('分页偏移量')
                ->min(0),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $filter = $request->string('filter', 'all');
        $category = $request->string('category');
        $tag = $request->string('tag');
        $sort = $request->string('sort');
        $reverse = $request->boolean('reverse');
        $limit = $request->integer('limit');
        $offset = $request->integer('offset');

        try {
            $qbittorrent = QbittorrentService::getInstance();

            $torrentsData = $qbittorrent->executeWithAuth(function ($client) use ($filter, $category, $tag, $sort, $reverse, $limit, $offset) {
                // 使用简化的方法调用，避免复杂的参数验证
                try {
                    // 构建查询参数
                    $params = [];

                    if ($filter && $filter !== 'all') {
                        $params['filter'] = $filter;
                    }
                    if ($category && ! empty(trim($category))) {
                        $params['category'] = trim($category);
                    }
                    if ($tag && ! empty(trim($tag))) {
                        $params['tag'] = trim($tag);
                    }
                    if ($sort && ! empty(trim($sort))) {
                        $params['sort'] = $sort;
                    }
                    if ($reverse !== null) {
                        $params['reverse'] = $reverse ? 'true' : 'false';
                    }
                    if ($limit !== null && $limit > 0 && $limit <= 1000) {
                        $params['limit'] = (int) $limit;
                    }
                    if ($offset !== null && $offset >= 0) {
                        $params['offset'] = (int) $offset;
                    }

                    // 直接调用 API 端点
                    $response = $client->torrents()->get('/info', $params);

                    if (! $response->isSuccess()) {
                        throw new \Exception('无法获取种子列表: '.implode(', ', $response->getErrors()));
                    }

                    $torrents = json_decode($response->getRawResponse(), true);
                    $result = [];

                    foreach ($torrents as $torrent) {
                        $result[] = [
                            'hash' => $torrent['hash'] ?? '',
                            'name' => $torrent['name'] ?? '',
                            'size' => $torrent['size'] ?? 0,
                            'formatted_size' => $this->formatBytes($torrent['size'] ?? 0),
                            'progress' => ($torrent['progress'] ?? 0),
                            'progress_percentage' => round(($torrent['progress'] ?? 0) * 100, 2).'%',
                            'download_speed' => $torrent['dl_speed'] ?? 0,
                            'formatted_download_speed' => $this->formatBytes($torrent['dl_speed'] ?? 0).'/s',
                            'upload_speed' => $torrent['up_speed'] ?? 0,
                            'formatted_upload_speed' => $this->formatBytes($torrent['up_speed'] ?? 0).'/s',
                            'downloaded' => $torrent['downloaded'] ?? 0,
                            'formatted_downloaded' => $this->formatBytes($torrent['downloaded'] ?? 0),
                            'uploaded' => $torrent['uploaded'] ?? 0,
                            'formatted_uploaded' => $this->formatBytes($torrent['uploaded'] ?? 0),
                            'ratio' => $torrent['ratio'] ?? 0,
                            'state' => $torrent['state'] ?? '',
                            'priority' => $torrent['priority'] ?? 0,
                            'num_seeds' => $torrent['num_seeds'] ?? 0,
                            'num_complete' => $torrent['num_complete'] ?? 0,
                            'num_leechs' => $torrent['num_leechs'] ?? 0,
                            'num_incomplete' => $torrent['num_incomplete'] ?? 0,
                            'category' => $torrent['category'] ?? '',
                            'tags' => $torrent['tags'] ?? '',
                            'save_path' => $torrent['save_path'] ?? '',
                            'added_time' => $torrent['added_on'] ?? 0,
                            'completion_time' => $torrent['completion_on'] ?? 0,
                            'tracker' => $torrent['tracker'] ?? '',
                            'dl_limit' => $torrent['dl_limit'] ?? 0,
                            'formatted_dl_limit' => ($torrent['dl_limit'] ?? 0) > 0 ? $this->formatBytes($torrent['dl_limit'] ?? 0).'/s' : '无限制',
                            'up_limit' => $torrent['up_limit'] ?? 0,
                            'formatted_up_limit' => ($torrent['up_limit'] ?? 0) > 0 ? $this->formatBytes($torrent['up_limit'] ?? 0).'/s' : '无限制',
                            'downloaded_session' => $torrent['downloaded_session'] ?? 0,
                            'uploaded_session' => $torrent['uploaded_session'] ?? 0,
                            'formatted_downloaded_session' => $this->formatBytes($torrent['downloaded_session'] ?? 0),
                            'formatted_uploaded_session' => $this->formatBytes($torrent['uploaded_session'] ?? 0),
                            'amount_left' => $torrent['amount_left'] ?? 0,
                            'formatted_amount_left' => $this->formatBytes($torrent['amount_left'] ?? 0),
                            'seeding_time' => $torrent['seeding_time'] ?? 0,
                            'finished' => ($torrent['state'] ?? '') === 'completed' || ($torrent['state'] ?? '') === 'stalled_uploading',
                            'stalled' => strpos($torrent['state'] ?? '', 'stalled') === 0,
                            'force_start' => $torrent['force_start'] ?? false,
                            'sequential_download' => $torrent['seq_dl'] ?? false,
                            'first_last_piece_prio' => $torrent['f_l_piece_prio'] ?? false,
                        ];
                    }

                    return [
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
                            'server_url' => QbittorrentService::getInstance()->getServerConfig()['base_url'] ?? 'unknown',
                            'username' => QbittorrentService::getInstance()->getServerConfig()['username'] ?? 'unknown',
                            'connected' => QbittorrentService::getInstance()->getServerConfig()['connected'] ?? false,
                        ],
                    ];

                } catch (\Exception $e) {
                    // 如果 UnifiedClient 方法失败，尝试更基本的方法
                    throw new \Exception('获取种子列表失败: '.$e->getMessage());
                }
            });

            return Response::text(json_encode($torrentsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } catch (\Exception $e) {
            Log::error('列出种子失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::error('列出种子失败: '.$e->getMessage());
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
