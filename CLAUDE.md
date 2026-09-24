# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 概要

投稿の一覧・編集・削除ができる小さな Laravel 10 アプリ（認証は Laravel Fortify、DB は MySQL、実行環境は Laravel Sail）。チュートリアル（Tutorial 13〜15）の教材として使われており、ドキュメントやコメント、コミットメッセージは日本語で書かれている。

## コマンド

すべて Sail（Docker）経由で実行する。ホストに PHP は前提としない。

```bash
./vendor/bin/sail up -d                          # 起動（http://localhost）
./vendor/bin/sail artisan migrate --seed         # テーブル作成 + 練習用データ投入
./vendor/bin/sail artisan migrate:fresh --seed   # DB を作り直す
./vendor/bin/sail artisan storage:link           # アバター画像の公開用リンク（各環境で最初に1回）
./vendor/bin/sail artisan test                   # 全テスト
./vendor/bin/sail artisan test --filter=メソッド名またはクラス名   # 単一テスト
./vendor/bin/sail bin pint                       # コード整形（Laravel Pint）
./vendor/bin/sail npm run dev                    # Vite 開発サーバー
./vendor/bin/sail down                           # 停止
```

- テストは `phpunit.xml` で `DB_DATABASE=testing` を使う。`testing` DB は Sail の MySQL コンテナ初回起動時に `create-testing-database.sh` で作られる。
- 初回起動直後の `migrate` で「Connection refused」が出たら MySQL の起動待ちなので、少し待って再実行する。

## アーキテクチャ

- **認証**: Fortify がログイン・登録・パスワードリセットのルートを提供する（`routes/web.php` には書かれていない）。有効な機能は `config/fortify.php` の `features`、ビューの割り当ては `FortifyServiceProvider`（`resources/views/auth/`）。ログイン後の遷移先は `/posts`（`config/fortify.php` の `home` と `RouteServiceProvider::HOME`）。
- **投稿**: `routes/web.php` の `auth` ミドルウェアグループ内に `index` / `edit` / `update` / `destroy` のみ。作成（create/store）と詳細（show）は未実装。
- **認可**: `PostPolicy` は `AuthServiceProvider::$policies` に登録されておらず、Laravel の自動検出で `Post` に紐づく。`PostController` で `$this->authorize()` を呼び、投稿者本人以外は 403。ビュー側でも `@can` で編集・削除ボタンを出し分けている。
- **データ**: `users` → `posts` ← `categories`（`posts` が `user_id` と `category_id` を持つ）。シーダー（`DatabaseSeeder`）はカテゴリ 3 件、ユーザー 2 人（`usera@example.com` / `userb@example.com`、パスワードは `password`）、各 2 件の投稿を作る。ファクトリは `UserFactory` のみ。

## `answers/` フォルダ

Tutorial 13（設計書の書き方）の解答例（Markdown と draw.io の図）で、アプリのコードとは無関係。アプリの機能追加・修正時には読む必要も変更する必要もない。受講者自身の設計書は `docs/` に置く想定（まだ存在しない場合がある）。
