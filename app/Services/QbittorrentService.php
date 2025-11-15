<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpQbittorrent\Client;
use PhpQbittorrent\Exception\AuthenticationException;
use PhpQbittorrent\Exception\NetworkException;

class QbittorrentService
{
    private static ?self $instance = null;

    private ?Client $client = null;

    private string $baseUrl;

    private string $username;

    private string $password;

    private function __construct()
    {
        $this->baseUrl = config('qbittorrent.url', env('QBITTORRENT_URL', 'http://localhost:8080'));
        $this->username = config('qbittorrent.username', env('QBITTORRENT_USERNAME', 'admin'));
        $this->password = config('qbittorrent.password', env('QBITTORRENT_PASSWORD', 'adminpass'));
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * 重置连接（用于测试或配置变更）
     */
    public static function reset(): void
    {
        if (self::$instance) {
            self::$instance->disconnect();
            self::$instance = null;
        }
    }

    /**
     * 获取已认证的客户端实例
     */
    public function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client($this->baseUrl, $this->username, $this->password);

            if (! $this->client->login()) {
                throw new \RuntimeException('无法连接到 qBittorrent 服务器，请检查连接配置');
            }
        }

        return $this->client;
    }

    /**
     * 检查连接状态
     */
    public function testConnection(): array
    {
        try {
            $client = $this->getClient();

            // 尝试获取版本信息来测试连接
            $versionRequest = \PhpQbittorrent\Request\Application\GetVersionRequest::create();
            $response = $client->application()->getVersion($versionRequest);

            if ($response->isSuccess()) {
                return [
                    'status' => 'success',
                    'version' => $response->getVersion(),
                    'message' => '连接成功',
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => '无法获取版本信息',
                ];
            }
        } catch (AuthenticationException $e) {
            return [
                'status' => 'error',
                'message' => '认证失败: '.$e->getMessage(),
            ];
        } catch (NetworkException $e) {
            return [
                'status' => 'error',
                'message' => '网络错误: '.$e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => '未知错误: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 安全地执行操作，自动处理登录和登出
     */
    public function executeWithAuth(callable $operation)
    {
        try {
            $client = $this->getClient();
            $result = $operation($client);

            return $result;
        } catch (\Exception $e) {
            Log::error('qBittorrent 操作失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取服务器配置信息
     */
    public function getServerConfig(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'username' => $this->username,
            'connected' => $this->client !== null,
        ];
    }

    /**
     * 断开连接
     */
    public function disconnect(): void
    {
        if ($this->client !== null) {
            try {
                $this->client->logout();
            } catch (\Exception $e) {
                Log::warning('qBittorrent 登出失败', [
                    'error' => $e->getMessage(),
                ]);
            }
            $this->client = null;
        }
    }

    /**
     * 析构函数 - 确保连接被正确关闭
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
