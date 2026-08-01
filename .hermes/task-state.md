# Task State

## Objective
Redditスレッド（5.6 Solの過剰設計対策）の知識を ~/Sites/chatter（Laravel 13 + Inertia + Svelte新規プロジェクト）に定着させる。AGENTS.md契約ファイルを作成し、今後の全AIセッションが過剰設計ルールを読むようにする。

## Scope
- `AGENTS.md` をリポジトリルートに新規作成（スレッド由来ルールを本スタック向けに具体化）
- `.hermes/task-state.md` に本作業の状態を記録（製品コミット対象外）

## Out of Scope
- git init / 初回コミット（ユーザー未指示）
- チャット機能の実装（未依頼）
- CLAUDE.md の別途作成（AGENTS.md でCodex/Claude双方カバー、重複回避）

## Acceptance Criteria
- [ ] AGENTS.md が ~/Sites/chatter 直下に存在し、数値化受入基準・結果判定レビューフィルタ・ゲート禁止・検証コマンドを含む
- [ ] 既存ファイルを変更していない（新規作成のみ）

## Stop Conditions
- ユーザーが内容や方針の変更を指示したら従う

## Decisions
| ID | 判断 | 理由 | 日時 |
|---|---|---|---|
| D-1 | 契約はAGENTS.md1本に集約（CLAUDE.md/.codexは作らない） | CodexとClaude Code双方がAGENTS.mdを読む。ファイル最小化 | 2026-08-01 |
| D-2 | 英語で記述 | AIエージェント向け契約ファイルの標準。具体性重視 | 2026-08-01 |
| D-3 | スレッド由来ルールをスタック固有（Pint/PHPStan/npm）の検証コマンドと結合 | 抽象的な「シンプルに」禁止より実行可能な受入基準が有効（スレッド最有力派） | 2026-08-01 |

## Completed Work
- AGENTS.md 作成（proportional engineering、3行プラン、ファイル数予算、ゲート禁止、レビューフィルタ、検証コマンド表、モデル配分）

## Pending Work
- なし

## Last Verified State
- Git HEAD: (gitリポジトリ未初期化)
- Working tree: AGENTS.md 追加のみ
- Test command: n/a（ドキュメント作成のみ）
- Last updated: 2026-08-01
