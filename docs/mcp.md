# qbittorrent MCP功能

## 常用命令
```bash
npx @modelcontextprotocol/inspector
npx @modelcontextprotocol/inspector php artisan mcp:serve --transport=stdio
php artisan mcp:start --transport=http


```
## tool列表(一行一个工具)
- ✅ 查看服务器信息 (ServerInfoTool) - 应用版本/Api版本/build信息/默认保存位置
- ✅ 获取应用设置 (GetAppPreferencesTool) - 获取应用偏好设置
- ✅ 获取主要数据/全局传输信息 (GetMainDataTool) - 获取全局传输统计
- ✅ 获取种子节点数据 (GetTorrentPeersDataTool) - 获取种子连接节点信息
- ✅ 获取全局下载/上传限制 (GetGlobalLimitsTool)
- ✅ 列出种子 (ListTorrentsTool) - 支持多种过滤和排序选项
- ✅ 获取torrent通用属性 (GetTorrentPropertiesTool) - 获取详细属性、文件列表和Tracker信息
- ✅ 获取torrent trackers (GetTorrentTrackersTool) - 获取Tracker状态、连接数和统计数据
- ✅ 获取torrent web seeds (GetTorrentWebSeedsTool) - 获取Web Seed信息
- ✅ 获取种子内容 (GetTorrentContentsTool) - 获取文件列表、大小、进度和优先级
- ✅ 获取torrent片段的状态 (GetTorrentPieceStatesTool) - 获取片段下载状态信息
- ✅ 获取torrent片段的hash (GetTorrentPieceHashesTool) - 获取片段哈希值用于完整性验证
- 暂停种子
- 重新校验种子
- Reannounce torrents
- 使用磁力链接创建种子下载
- Add trackers to torrent
- 暂停所有种子
- 恢复所有种子
- 切换备选速度限制

## 🆕 重构完成说明

### 架构改进 (2025-11-16)
- **实现了"一个工具一个类"的设计原则**
- **将 TorrentDetailTool 拆分为5个独立专用工具类**
- **提升了代码可维护性和可扩展性**

### 新拆分的专用工具类
1. **GetTorrentTrackersTool** - 专门处理Tracker信息获取
2. **GetTorrentWebSeedsTool** - 专门处理Web Seed信息获取
3. **GetTorrentContentsTool** - 专门处理文件列表获取
4. **GetTorrentPieceStatesTool** - 专门处理片段状态获取
5. **GetTorrentPieceHashesTool** - 专门处理片段哈希获取

### 重构优势
- ✅ **单一职责原则** - 每个工具类专注特定功能
- ✅ **模块化设计** - 代码结构清晰，易于维护
- ✅ **独立开发** - 可以独立修改和测试各个工具
- ✅ **功能完整** - 保持了原有所有功能
- ✅ **测试验证** - 主要功能已通过测试验证

## 详述

### 查看服务器信息 (ServerInfoTool/get_server_info)
查看 qBittorrent 服务器基本信息，包括：
- 应用版本号
- Web API 版本
- 构建信息（Qt、libtorrent、Boost版本）
- 默认保存路径
- 连接配置信息

### 获取应用设置 (GetAppPreferencesTool/get_app_preferences)
获取 qBittorrent 的完整应用设置，包括：
- 下载设置（保存路径、任务限制等）
- 速度限制设置
- BitTorrent 协议设置
- 界面和语言设置
- 网络连接设置

### 获取主要数据/全局传输信息 (GetMainDataTool/get_main_data)
获取全局传输统计信息：
- 实时下载/上传速度
- 全局速度限制
- 累计下载/上传量
- DHT节点数量
- 网络连接状态
- 格式化的速度和数据量显示

### 获取种子节点数据 (GetTorrentPeersDataTool/get_torrent_peers_data)
获取指定种子的连接节点信息：
- 需要提供种子哈希值
- 返回连接的所有peer列表
- 包含每个peer的IP、端口、客户端信息
- 传输速度和进度统计

### 获取全局下载/上传限制 (GetGlobalLimitsTool/get_global_download_limit&get_global_upload_limit)
获取 qBittorrent 全局下载和上传速度限制：

**get_global_download_limit**：
- 返回当前下载速度限制值
- 提供格式化的限制值（KB/MB/GB per second）
- 包含限制级别描述（无限制/低速/中速/高速）
- 显示限制状态（是否启用限制）

**get_global_upload_limit**：
- 返回当前上传速度限制值
- 提供格式化的限制值（KB/MB/GB per second）
- 包含限制级别描述（无限制/低速/中速/高速）
- 显示限制状态（是否启用限制）