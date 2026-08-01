# Task State — chatter (Discord-style groupware)

## Objective

`~/Sites/chatter` に、Laravel Svelte Starter Kit + askdkc/breezejp ベースの
「Discord風チャットベースグループウェア」を新規作成する。jin のファイルアップロード/
OnlyOffice プレビュー/ガント実装は流用し、UI は Discord 風に再設計する。

## Scope

- 新規プロジェクト `/home/ubuntu/Sites/chatter`（Laravel 13 svelte-starter-kit）
- 要件: サーバー(=Project)隔離 / チャンネル(タスク化: 開始日・終了期限) /
  スレッド / D&D ファイルアップロード / ファイルビューワーUI(ポップアップ) /
  画像・動画・PDF JSプレビュー / Office は OnlyOffice 読み取り専用 /
  サーバー開始日・期限 / チャンネル内 todo / サーバー・チャンネル内タスク一覧UI /
  カレンダー + ガントチャート(サーバー=全チャンネルタスク、チャンネル=配下タスク) /
  タスク期限リマインダーのチャンネル通知
- jin からの流用: OnlyOffice 一式（ConfigService/DocumentTypeResolver/
  DocumentVersion/TokenService/FileController/PreviewController）,
  StoredFile モデルとプレビュー/アップロード、ガント UI ロジック、D&D アップロード
- 日本語UI（breezejp + 独自lang）

## Out of Scope

- jin 本体の変更・push
- kintone / Teams 連携
- マルチテナント間の横断検索・クロスサーバー通知
- 本番デプロイ設定（ローカル動作確認まで）
- 既存 jin の機能移植すべて（チャット/タスク/ガント/ファイル/OnlyOffice に限定）

## Acceptance Criteria

- [ ] `composer create-project laravel/svelte-starter-kit chatter` が動作する
- [ ] breezejp 日本語化がログイン/登録画面に反映
- [ ] サーバー(=プロジェクト)作成・一覧・メンバーシップが動作
- [ ] チャンネル作成・タスク化（開始日/終了期限）が可能
- [ ] チャット送信・スレッド・チャンネル切替が Reverb で動作
- [ ] チャットへ D&D ファイルアップロード → メッセージに添付表示
- [ ] ファイルビューワーUI: 画像/動画/PDF は JS プレビュー、Office は OnlyOffice 読取専用（ポップアップ）
- [ ] サーバー/チャンネル内タスク一覧UI
- [ ] サーバー/チャンネル別カレンダー + ガントチャート
- [ ] タスク期限リマインダーがチャンネルへ通知
- [ ] テスト: 主要フロー feature test 通過 / npm build / lint / types:check
- [ ] `.hermes/task-state.md` が最終状態と一致

## Stop Conditions

- スターターキット作成失敗（ネットワーク/依存解決）→ 報告して代替を相談
- OnlyOffice ドキュメントサーバーが無い環境ではモック/無効化で検証
- 同一仮説3回失敗 → 中断して報告

## Current Hypothesis

jin の Support クラス群と StoredFile プレビュー機構を移植すれば、
OnlyOffice/プレビュー/ガントの再実装コストを大幅に削減できる。

## Coverage Matrix

| ID | Case | Reproduction | Status | Test | Evidence |
|---|---|---|---|---|---|
| C-001 | スターターキット作成+breezejp | composer create-project | 未対応 | smoke | — |
| C-002 | サーバーCRUD+メンバー | ブラウザ操作 | 未対応 | feature | — |
| C-003 | チャンネルタスク化 | フォームで開始日/期限設定 | 未対応 | feature | — |
| C-004 | チャット+スレッド+Reverb | 2ユーザー送信 | 未対応 | feature+echo | — |
| C-005 | D&D アップロード | チャット欄へドロップ | 未対応 | feature | — |
| C-006 | プレビュー(画像/動画/PDF/Office) | ポップアップ表示 | 未対応 | feature+js | — |
| C-007 | タスク一覧UI(サーバー/チャンネル) | ページ表示 | 未対応 | feature | — |
| C-008 | カレンダー+ガント | ページ表示 | 未対応 | feature | — |
| C-009 | 期限リマインダー通知 | スケジューラ実行 | 未対応 | feature | — |

## Decisions

| ID | Decision | Reason | Alternative | Date |
|---|---|---|---|---|
| D-001 | 新規プロジェクト chatter 作成 | ユーザー指示 | jin 拡張 | 2026-08-01 |
| D-002 | jin から OnlyOffice/プレビュー/ガントを移植 | 実装済み・検証済みの再利用 | 再実装 | 2026-08-01 |
| D-003 | UI は Discord 風に再設計 | jin は Slack 風のため | そのまま流用 | 2026-08-01 |

## Completed Work

- 調査: jin は Svelte starter kit + breezejp + チャット/ガント/OnlyOffice 実装済み
- 調査: `app/Support/` に OnlyOffice 関連5クラス + StoredFilePreviewDispatcher/Generator
- 調査: frontend deps: @file-viewer/web + preset-office, laravel-echo, shiki, trix

## Pending Work

- スターターキット作成 → breezejp → 移植 → Discord UI → 検証

## Last Verified State

- Git HEAD: (未作成)
- Working tree: (未作成)
- Test command: composer test / npm run types:check
- Test result: (未実行)
- Last updated: 2026-08-01
