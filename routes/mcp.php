<?php

use PhpMcp\Laravel\Facades\Mcp;
use App\Services\Mcp\Tools\ServerInfoTool;
use App\Services\Mcp\Tools\GetAppPreferencesTool;
use App\Services\Mcp\Tools\GetMainDataTool;
use App\Services\Mcp\Tools\GetTorrentPeersDataTool;
use App\Services\Mcp\Tools\GetGlobalLimitsTool;
use App\Services\Mcp\Tools\TorrentManagementTool;
use App\Services\Mcp\Tools\TorrentDetailTool;

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

// 注册获取全局限制工具
Mcp::tool([GetGlobalLimitsTool::class, 'getGlobalDownloadLimit'])
    ->name('get_global_download_limit')
    ->description('获取 qBittorrent 全局下载速度限制设置');

Mcp::tool([GetGlobalLimitsTool::class, 'getGlobalUploadLimit'])
    ->name('get_global_upload_limit')
    ->description('获取 qBittorrent 全局上传速度限制设置');

// 注册种子管理工具
Mcp::tool([TorrentManagementTool::class, 'listTorrents'])
    ->name('list_torrents')
    ->description('列出种子列表，支持多种过滤和排序选项：filter(all/downloading/completed/paused等), sort(按字段排序), category, tag, limit, offset');

Mcp::tool([TorrentManagementTool::class, 'getTorrentProperties'])
    ->name('get_torrent_properties')
    ->description('获取指定种子(按hash)的详细属性：基本信息、网络状态、文件列表、存储路径、时间信息等');

// 注册种子详细信息工具
Mcp::tool([TorrentDetailTool::class, 'getTorrentTrackers'])
    ->name('get_torrent_trackers')
    ->description('获取指定种子的所有Tracker信息，包括状态、连接数和统计数据');

Mcp::tool([TorrentDetailTool::class, 'getTorrentWebSeeds'])
    ->name('get_torrent_web_seeds')
    ->description('获取指定种子的Web Seed信息');

Mcp::tool([TorrentDetailTool::class, 'getTorrentContents'])
    ->name('get_torrent_contents')
    ->description('获取指定种子的文件列表信息，包括大小、进度和优先级');

Mcp::tool([TorrentDetailTool::class, 'getTorrentPieceStates'])
    ->name('get_torrent_piece_states')
    ->description('获取指定种子的片段下载状态信息');

Mcp::tool([TorrentDetailTool::class, 'getTorrentPieceHashes'])
    ->name('get_torrent_piece_hashes')
    ->description('获取指定种子的片段哈希值，用于完整性验证');