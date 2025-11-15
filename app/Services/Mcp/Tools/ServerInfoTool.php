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
 * qBittorrent 服务器信息工具
 *
 * 获取 qBittorrent 服务器的完整信息，包括应用版本、API版本、构建信息和默认保存位置
 */
#[IsReadOnly]
#[IsIdempotent]
class ServerInfoTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = '获取 qBittorrent 服务器的完整信息，包括应用版本、API版本、构建信息和默认保存位置';

    /**
     * The tool's name.
     */
    protected string $name = 'get_server_info';

    /**
     * The tool's title.
     */
    protected string $title = 'Get qBittorrent Server Info';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            // 此工具不需要输入参数
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $qbittorrent = QbittorrentService::getInstance();

            $serverInfo = $qbittorrent->executeWithAuth(function ($client) use ($qbittorrent) {
                // 获取应用版本
                $versionRequest = \PhpQbittorrent\Request\Application\GetVersionRequest::create();
                $versionResponse = $client->application()->getVersion($versionRequest);
                $version = $versionResponse->isSuccess() ? $versionResponse->getVersion() : '未知';

                // 获取 Web API 版本
                $apiVersionRequest = \PhpQbittorrent\Request\Application\GetWebApiVersionRequest::create();
                $apiVersionResponse = $client->application()->getWebApiVersion($apiVersionRequest);
                $apiVersion = $apiVersionResponse->isSuccess() ? $apiVersionResponse->getVersion() : '未知';

                // 获取构建信息
                $buildInfoRequest = \PhpQbittorrent\Request\Application\GetBuildInfoRequest::create();
                $buildInfoResponse = $client->application()->getBuildInfo($buildInfoRequest);
                $buildInfo = $buildInfoResponse->isSuccess() ? $buildInfoResponse->getBuildInfo() : [];

                // 获取默认保存位置
                $savePath = '';
                try {
                    $preferencesRequest = \PhpQbittorrent\Request\Application\GetPreferencesRequest::create();
                    $preferencesResponse = $client->application()->getPreferences($preferencesRequest);
                    if ($preferencesResponse->isSuccess()) {
                        $preferences = $preferencesResponse->getData()['preferences'] ?? [];
                        $savePath = $preferences['save_path'] ?? '';
                    }
                } catch (\Exception $e) {
                    Log::warning('无法获取默认保存位置', ['error' => $e->getMessage()]);
                    $savePath = '未知';
                }

                return [
                    'application_version' => $version,
                    'api_version' => $apiVersion,
                    'build_info' => $buildInfo,
                    'default_save_path' => $savePath,
                    'connection_config' => [
                        'server_url' => $qbittorrent->getServerConfig()['base_url'] ?? 'unknown',
                        'username' => $qbittorrent->getServerConfig()['username'] ?? 'unknown',
                        'connected' => $qbittorrent->getServerConfig()['connected'] ?? false,
                    ],
                ];
            });

            return Response::text(json_encode($serverInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        } catch (\Exception $e) {
            Log::error('获取服务器信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::text('获取服务器信息失败: '.$e->getMessage());
        }
    }
}
