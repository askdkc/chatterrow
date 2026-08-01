# Task State — chatter Release-Blocking Defects Remediation (PLAN.md)

## Objective

PLAN.md（21件のリリースブロッカー C-001〜C-021）を TDD でタスク1〜14 の順に修正し、全受け入れ基準を満たして CI をグリーンにする。

## Scope

- タスク2: CSRF 復旧（http.ts / csrf meta / 419 排除）+ create policy 署名修正 + ChannelDialog JSON 契約
- タスク3: Inertia ページ props 契約の正規化（auth.servers 単一ソース）
- タスク4: MessageResource / StoredFileResource / スレッド / ソフトデリート tombstone
- タスク5: ファイル所有権・safe inline・content_sha256・StoredFilePolicy
- タスク6: staged/channel アップロード・StoredFileCleanupService・プレビュー teardown
- タスク7: OnlyOffice 閲覧者バインド・JWT 期限・ドキュメントキー・OfficeDocumentInspector
- タスク8: gantt.ts 日付計算・グリッドレイアウト・チャンネルガントページ化
- タスク9: リマインダー再スケジュール・原子的冪等（transaction claim）
- タスク10: Todo テナント境界・アカウント削除時のサーバー所有権ブロック
- タスク11: broadcastAs() 明示名・リアルタイム収束・TodoDeleted
- タスク12: Server/Channel CRUD UI 完成・aria-live・アクセシビリティ
- タスク13: CI ゲート（Pint/PHPStan/Prettier/Svelte/audit）復旧・README/.env.example 更新
- タスク14: 最終 E2E 受け入れ

## Out of Scope

- OnlyOffice 編集・コメント（読取専用のまま）
- ビジュアル再設計・新ロール・ネイティブアプリ
- 本番デプロイ・本番 DB 操作・本番シークレット

## Acceptance Criteria

- [ ] 認証ユーザーが 419 なしで server/channel/message/Todo/member/file を作成できる
- [ ] create Policy TypeError が解消される
- [ ] ChannelDialog が JSON 201 で navigates する（HTML redirect パースしない）
- [ ] 非メンバー 403/404、非オーナーはメンバー管理不可、非アップローダーはファイル削除不可
- [ ] HTML/SVG がインライン実行されない
- [ ] メッセージ添付に stream/download/thumbnail URL が含まれ、storage パスを晒さない
- [ ] スレッド読込/返信/リロードで reply_count が保持される
- [ ] 親削除は tombstone、他ユーザーの返信は残る
- [ ] Tasks/Gantt/Files が undefined 無しでレンダリングされる
- [ ] ガントの日付が JST で DB と一致、バーが日付列に揃う
- [ ] メンバー除外後は OnlyOffice URL が使えない
- [ ] JWT 期限・デプロイメントスコープドキー・構造検証 fail-closed
- [ ] 日付変更で新しい日付に 1 件だけリマインダー（同時実行でも重複なし）
- [ ] staged 期限切れ・削除で DB 行と blob 両方消える / 失敗時はリトライ台帳
- [ ] Todo アサイニーは同一サーバー内のみ
- [ ] サーバー所有アカウントは削除不可
- [ ] broadcastAs() と Echo リスナーが一致、作成/削除で収束
- [ ] プレビュー teardown（close/error/timeout）でインスタンス破棄
- [ ] CRUD UI 到達可能・エラーが aria-live で可視・キーボード/読み上げ対応
- [ ] composer ci:check / test:unit / build / audit がクリーンワークツリーから green

## Stop Conditions

- 本番資格情報・本番データアクセスが必要になったら停止
- OnlyOffice サーバーが手動検証不可でも自動テストは継続（外部受入は pending 明記）
- 同一仮説 3 回失敗 → 停止して報告
- 受け入れ基準を超える変更は具体的失敗ケースが無ければ追加しない

## Current Hypothesis

タスク1（ベースライン記録）→ タスク2（CSRF + Policy + Channel JSON）から開始。既存 43 テストは green、lint/types は前回修正済みだが PHPStan/Prettier/audit は未検証。

## Coverage Matrix

| ID | ケース | タスク | 対応状態 | テスト | 根拠 |
|---|---|---|---|---|---|
| C-001 | CSRF 空トークン 419 | 2 | 未対応 | ApplicationShellTest | PLAN.md |
| C-002 | create policy 型不一致 | 2 | 未対応 | GroupwareMutationAuthorizationTest | PLAN.md |
| C-003 | auth.servers/authServers 不一致 | 3 | 未対応 | GroupwarePagePropsTest | PLAN.md |
| C-004 | 添付 URL リソース欠落 | 4 | 未対応 | MessageAttachmentResourceTest | PLAN.md |
| C-005 | 返信が読めない/カウント非永続 | 4 | 未対応 | MessageThreadTest | PLAN.md |
| C-006 | クライアントが添付メタ操作 | 5 | 未対応 | MessageAttachmentClaimTest | PLAN.md |
| C-007 | HTML/SVG インライン実行・他者削除 | 5 | 未対応 | StoredFileSecurityTest | PLAN.md |
| C-008 | staged 孤立・チャンネルアップロード消滅 | 6 | 未対応 | StagedUploadTest | PLAN.md |
| C-009 | 削除で blob 残存・失敗無視 | 6 | 未対応 | StoredFileLifecycleTest | PLAN.md |
| C-010 | OnlyOffice URL 生存・JWT/キー弱 | 7 | 未対応 | OnlyOfficeSecurityTest | PLAN.md |
| C-011 | ガント日付/グリッド計算不正 | 8 | 未対応 | gantt.test.ts | PLAN.md |
| C-012 | リマインダー非再スケジュール/非冪等 | 9 | 未対応 | DueDateReminderTest | PLAN.md |
| C-013 | 外部アサイニー・オーナー削除孤児化 | 10 | 未対応 | TodoAssigneeAuthorizationTest | PLAN.md |
| C-014 | ブロードキャスト名不一致 | 11 | 未対応 | BroadcastContractTest | PLAN.md |
| C-015 | CRUD UI 欠落・デッドコールバック | 12 | 未対応 | GroupwareDialogs.test.ts | PLAN.md |
| C-016 | CI 赤 | 13 | 未対応 | ci:check | PLAN.md |
| C-017 | 親削除が他ユーザー返信を巻き添え | 4 | 未対応 | MessageThreadTest | PLAN.md |
| C-018 | チャンネル作成 HTML redirect | 2 | 未対応 | ChannelDialog.test.ts | PLAN.md |
| C-019 | プレビュー teardown 欠落 | 6 | 未対応 | StoredFilePreviewDialog.test.ts | PLAN.md |
| C-020 | Chat/Todo/File 失敗が無言 | 2,12 | 未対応 | OperationErrors.test.ts | PLAN.md |
| C-021 | キーボード/読み上げ不能 | 12 | 未対応 | GroupwareDialogs.test.ts | PLAN.md |

## Decisions

| ID | 判断 | 理由 | 代替案 | 日時 |
|---|---|---|---|---|
| D-001 | コミットは各タスク完了毎に 1 コミット | PLAN.md の commit policy | — | 2026-08-01 |
| D-002 | TDD（RED→GREEN）を厳守 | PLAN.md 冒頭 + test-driven-development スキル | — | 2026-08-01 |

## Completed Work

- タスク1: ベースライン記録（下記 Last Verified State）

## Pending Work

- タスク2〜14 すべて

## Last Verified State

- Git HEAD: 0d6e2a8 (main)
- Working tree: clean（.hermes/ は非コミット対象）
- ベースラインゲート: 下記実行結果（タスク1 ステップ3）

---

## ベースラインゲート結果（タスク1）

| コマンド | 結果 |
|---|---|
| php artisan test | 43 passed (149 assertions) — 確認済み（前回） |
| npm run lint:check | ✅ 前回修正済み |
| npm run types:check | ✅ 0 エラー（前回） |
| npm run format:check | ⚠️ 未検証 |
| composer lint:check (pint) | ⚠️ 未検証 |
| composer types:check (phpstan) | ⚠️ 未検証（PLAN は 4 ファイル 6 findings と記載） |
| npm run build | ✅ 16.17s（前回） |
| composer audit --locked | ⚠️ 未検証 |
| npm audit --audit-level=high | ⚠️ 未検証 |

*実測値はタスク開始時に再実行して確定する。*
