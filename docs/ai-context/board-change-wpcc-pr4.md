# [CX] DevOps Board Change：WP Chinese Converter PR #4

## 标题

WP Chinese Converter PR #4：推送本地候选并审批 exact-HEAD CI（禁止合并、发布、部署）

## Board 字段

- `family`: `change`
- `owner`: `devops`
- `priority`: `high`
- `workspace`: `wp-plugins`
- `required_capabilities`: `["wordpress_dev"]`（若 registry 存在更精确的 Forgejo CI 能力，以 registry 真值替换）
- `project_root`: `/home/parallels/Projects/wp-chinese-converter-codex-audit`
- `review_required`: `true`
- `decision_mode`: `manual_review`

## task_args.task

在 feicode-prod 的 P0 `write_policy=board_required` 边界内，仅处理 WenPai VM 的 WP Chinese Converter 本地候选。先记录候选分支、local HEAD、clean status、PR #4 当前 remote HEAD 和目标默认分支；工作树不干净、远端发生未知分叉或候选 HEAD 与交付报告不一致时立即停止。

本 change 经 devops 审核后，只允许普通、非 force push，把获准的 exact local HEAD 推送到 PR #4 既有的非默认候选分支；只允许审批或触发绑定该 exact commit SHA 的 CI。不得修改默认分支，不得 force-push，不得合并 PR，不得创建 tag/release，不得上传正式发布资产，不得调用 fei-publish、Woo 或 mall 管线，不得部署，不得改变 WAF、主机、Actions runner 或仓库权限。

CI 若发生 SHA 漂移、包含发布或部署步骤、要求生产 secret/环境批准，或可能触及 commerce，立即停止并退回人工审核。凭据只从既有 secret store/env 读取，不输出、不写仓库。完成后把 remote ref、PR head、CI run URL/id、workflow commit SHA、结论和未通过发布门写入 Board result；任务进入 review，只由 devops 审核。

## 验收标准

1. 推送前证据包含 branch、local SHA、clean status、remote SHA；发现分叉或不一致即 FAIL 并停止。
2. 仅 PR #4 既有非默认候选分支发生普通 push；`remote branch SHA == approved local HEAD == PR head SHA == CI source SHA`。
3. CI 只对 exact HEAD 运行。分别记录 PHPUnit、PHPStan、Plugin Check、standalone single-site、multisite、Yoast、E2E、恢复/幂等、可复现 ZIP；零样本记 SKIP/FAIL，不写 PASS。
4. CI ZIP 记录文件名、版本、字节数、entry 数、SHA-256，并与本地候选构建比对。artifact 只作候选证据，不创建 Forgejo Release。
5. 核验默认分支、tag/release、部署环境、fei-publish、Woo、mall 均未改变；workflow 若包含这些步骤，不得批准。
6. Board task 完成后进入 `review`；review note 写 exact SHA、CI conclusion、remaining blockers 和权限边界。CI 失败时不得降低门槛或绕过检查。

## 停止与回滚

- push 前失败：不写 FeiCode，保留本地候选与日志，任务标 failed/review。
- 未授权 SHA 被推送：停止后续 CI/审批，不删历史、不 force-reset；由 devops 决定追加 revert commit 或建立新的纠正候选。删除远端分支必须另开审批。
- exact-HEAD CI 已批准但尚未运行：取消该 run；已经运行则保留审计结果，不以另一 SHA 的结果冒充。
- workflow 会触发 tag/release/deploy/commerce：不得批准，记录 workflow 路径与触发条件后退回人工处理。
- WAF/IP 受阻：从第二路径只读核验，不关闭 WAF、不修改 allowlist；另开基础设施 change。
