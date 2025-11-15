<?php

use PhpMcp\Laravel\Facades\Mcp;
use App\Services\Mcp\Tools\ServerInfoTool;
use App\Services\Mcp\Tools\GetAppPreferencesTool;
use App\Services\Mcp\Tools\GetMainDataTool;
use App\Services\Mcp\Tools\GetTorrentPeersDataTool;

// 注册服务器信息工具
Mcp::tool([ServerInfoTool::class, 'getServerInfo'])
    ->name('get_server_info')
    ->description('查看 qBittorrent 服务器信息，包括应用版本、API版本、构建信息和默认保存位置');

// 注册获取应用设置工具
Mcp::tool([GetAppPreferencesTool::class, 'getAppPreferences'])
    ->name('get_app_preferences')
    ->description('获取 qBittorrent 应用偏好设置，包括下载、上传、界面等配置');

// 注册获取主要数据工具
Mcp::tool([GetMainDataTool::class, 'getMainData'])
    ->name('get_main_data')
    ->description('获取 qBittorrent 全局传输信息，包括上下行速度、限制和统计数据');

// 注册获取种子节点数据工具
Mcp::tool([GetTorrentPeersDataTool::class, 'getTorrentPeersData'])
    ->name('get_torrent_peers_data')
    ->description('获取 qBittorrent 种子的 Peers 连接信息和统计数据');