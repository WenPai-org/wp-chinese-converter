# 区块功能状态说明

## 当前状态
区块功能已暂时禁用，相关文件已移动到 `disabled` 目录中。

## 移动的文件
- `includes/core/class-wpcc-blocks.php` → `includes/disabled/class-wpcc-blocks.php`
- `assets/js/gudengbao.js` → `assets/disabled/gudengbao.js`
- `assets/css/blocks.css` → `assets/disabled/blocks.css`

## 禁用原因
- 区块在古腾堡编辑器中无法正常显示
- 需要先确保插件核心功能稳定运行

## 核心功能保留
以下功能仍然正常工作：
- 中文简繁体转换
- 语言切换导航
- 短代码 `[wp-chinese-converter]`
- 管理后台设置
- URL重写和语言检测

## 恢复计划
1. 确保核心功能稳定
2. 分析区块注册问题
3. 重新设计区块架构
4. 逐步恢复区块功能

## 恢复步骤
当需要恢复区块功能时：
1. 将文件从 `disabled` 目录移回原位置
2. 在主插件文件中重新启用区块初始化
3. 测试区块注册和显示

## 替代方案
在区块功能恢复之前，用户可以使用：
- 短代码：`[wp-chinese-converter]`
- Widget：在外观 > 小工具中添加
- 模板函数：`<?php wpcc_output_navi(); ?>`