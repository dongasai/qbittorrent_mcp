<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;

class qBittorrentServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'qBittorrentMcp';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        # qBittorrent MCP 服务器

        这是一个用于管理 qBittorrent 下载任务的 MCP 服务器。

        ## 可用工具

        ### get_server_info
        获取 qBittorrent 服务器的完整信息，包括：
        - 应用版本和 API 版本
        - 构建信息（Qt、libtorrent、Boost 等版本）
        - 默认保存路径
        - 当前连接配置

        ### get_app_preferences
        获取 qBittorrent 应用的完整偏好设置，包括：
        - 下载设置（保存路径、最大连接数等）
        - 速度限制设置（上传/下载速度限制）
        - BitTorrent 设置（DHT、PEX、加密等）
        - 界面设置（语言、删除确认等）
        - 连接设置（端口、SSL等）
        - 高级设置（缓存、跟踪器等）

        ### get_global_limits
        获取 qBittorrent 的全局上传和下载速度限制，包括：
        - 下载限制（原始值、格式化值、限制级别）
        - 上传限制（原始值、格式化值、限制级别）
        - 整体状态摘要（是否有任何限制、是否都受限、是否都无限制）
        - 连接信息

        ### get_main_data
        获取 qBittorrent 的全局传输信息和统计数据，包括：
        - 实时下载/上传速度
        - 全局速度限制设置
        - 累计下载/上传数据量
        - 连接状态和节点信息
        - 格式化的可读数据展示

        ### list_torrents
        获取 qBittorrent 中的种子列表，支持多种过滤和排序选项：
        - 过滤选项：all, downloading, completed, paused, active, inactive, resumed, stalled 等
        - 排序字段：hash, name, size, progress, dl_speed, up_speed, priority 等
        - 分页支持：limit 和 offset 参数
        - 分类和标签过滤
        - 详细的种子信息：进度、速度、大小、状态、连接数等

        ### get_torrent_peers_data
        获取指定种子的 Peers 连接信息，包括：
        - Peer 列表和详细信息（IP、端口、客户端）
        - 连接状态和标志位分析
        - 传输统计（下载/上传速度和总量）
        - 进度信息和来源分析
        - 汇总统计数据和平均值计算

        ### get_torrent_properties
        获取指定种子的详细属性信息，包括：
        - 基本信息：名称、大小、进度、状态、优先级等
        - 网络信息：下载/上传速度、连接数、做种数、分享比等
        - 存储信息：保存路径、分类、标签、速度限制等
        - 时间信息：添加时间、完成时间、活动时间等
        - 文件列表：包含所有文件的索引、名称、大小、进度、优先级
        - 种子属性：创建信息、注释、浪费数据、分片信息等

        ### get_torrent_trackers
        获取指定种子的所有Tracker信息，包括：
        - Tracker URL和工作状态
        - 连接数、种子数、下载数
        - Tracker消息和层级信息
        - Tracker状态描述（正常工作、正在更新、不可用等）

        ### get_torrent_web_seeds
        获取指定种子的Web Seed信息，包括：
        - Web Seed URL列表
        - Web Seed数量统计

        ### get_torrent_contents
        获取指定种子的文件列表信息，包括：
        - 种子包含的所有文件
        - 文件大小、进度、优先级
        - 支持大种子的分页查询
        - 总体进度统计和格式化显示

        ### get_torrent_piece_states
        获取指定种子的片段状态信息，包括：
        - 每个片段的下载状态
        - 下载进度统计
        - 支持范围查询以处理大型种子

        ### get_torrent_piece_hashes
        获取指定种子的片段哈希值，包括：
        - 每个片段的SHA1哈希
        - 用于完整性验证
        - 支持分页查询

        ## 使用说明

        1. 所有工具都支持 JSON-RPC 2.0 协议
        2. 服务器会自动处理 qBittorrent 的认证
        3. 返回的数据均为 JSON 格式，支持中文字符
        4. 如果操作失败，会在日志中记录详细信息

        ## 注意事项

        - 确保已正确配置 qBittorrent 服务器连接
        - 服务器地址、用户名和密码需要在 .env 文件中配置
        - 所有操作都是幂等的，重复调用不会产生副作用

        ## 错误处理

        如果遇到错误，请检查：
        1. qBittorrent 服务是否运行
        2. 网络连接是否正常
        3. 认证信息是否正确

    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        \App\Services\Mcp\Tools\ServerInfoTool::class,
        \App\Services\Mcp\Tools\GetAppPreferencesTool::class,
        \App\Services\Mcp\Tools\GetGlobalLimitsTool::class,
        \App\Services\Mcp\Tools\GetMainDataTool::class,
        \App\Services\Mcp\Tools\GetTorrentPeersDataTool::class,
        \App\Services\Mcp\Tools\ListTorrentsTool::class,
        \App\Services\Mcp\Tools\GetTorrentPropertiesTool::class,
        \App\Services\Mcp\Tools\GetTorrentTrackersTool::class,
        \App\Services\Mcp\Tools\GetTorrentWebSeedsTool::class,
        \App\Services\Mcp\Tools\GetTorrentContentsTool::class,
        \App\Services\Mcp\Tools\GetTorrentPieceStatesTool::class,
        \App\Services\Mcp\Tools\GetTorrentPieceHashesTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
