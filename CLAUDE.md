# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 概要

投稿の一覧・編集・削除、リプライ、プロフィール（アバター画像つき）が使える小さな Laravel 10 アプリ（認証は Laravel Fortify、DB は MySQL、実行環境は Laravel Sail）。チュートリアル（Tutorial 13〜15）の教材として使われており、ドキュメントやコメント、コミットメッセージは日本語で書かれている。

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

- **認証**: Fortify がログイン・登録・パスワードリセットのルートを提供する（`routes/web.php` には書かれていない）。有効な機能は `config/fortify.php` の `features`、ビューの割り当ては `FortifyServiceProvider`（`resources/views/auth/`）。ログイン直後は、ログイン前に開こうとしたページに関わらず常に `/posts` へ移動する（`FortifyServiceProvider::register()` で、Fortify 標準の `intended` を使う応答を `App\Http\Responses\LoginResponse` に差し替えている。`config/fortify.php` の `home` と `RouteServiceProvider::HOME` も `/posts`）。
- **投稿**: `routes/web.php` の `auth` ミドルウェアグループ内に `index` / `show` / `create` / `store` / `edit` / `update` / `destroy`（`/posts/create` は `/posts/{post}` より前に書かないと 404 になる）。新規投稿は、ヘッダーの「新規投稿」から `posts.create`。新規投稿と編集は、フォームを `posts/_form.blade.php` で、入力チェックを `PostController::validatePost`（日本語のメッセージ）で共有している。投稿者は `$request->user()->posts()->create()` で決め、画面から `user_id` は受け取らない。本文は 140 字以内。タイムラインのポスト 1 件の表示は `resources/views/posts/_post.blade.php` にあり、プロフィールのポスト一覧と共有している。タイトルの横のリプライ件数 `(N)` は `withCount('replies')` で数える。
- **リプライ**: ポスト直下の 1 階層のみ（リプライへのリプライはない）。詳細ページ `posts.show` に一覧（古い順）と送信フォームがあり、送信は `replies.store`、削除は `replies.destroy`（`ReplyController`）。本文は必須・140 字以内で、メッセージは日本語。ポストを消すとリプライも一緒に消える（外部キーの `cascadeOnDelete`）。
- **プロフィール**: `ProfileController`。`users.show`（`/users/{user}`）はログインしていれば誰のものでも見られ、ポスト一覧とリプライ一覧を `?tab=replies` で切り替える。編集は `profile.edit` / `profile.update`（`/profile/...`）で、ルートに `{user}` を持たせず常にログイン中の本人だけが対象（Policy は使わない）。メールは本人が自分のプロフィールを見るときだけ表示する。自己紹介 `bio` は 140 字以内。
- **アバター画像**: プロフィール編集でアップロードする。`App\Services\AvatarStorage` が、向きを補正 → 中央を正方形に切り抜き → 256×256 の WebP に加工して `public` ディスクの `avatars/` に保存し、パスを `users.avatar_path` に入れる（GD を使う。EXIF は残らない）。受け付けるのは JPEG / PNG / WebP / GIF・5MB 以下・縦横 5000px 以下（SVG と HEIC は不可）。URL は `User::avatarUrl()`、表示は Blade コンポーネント `<x-avatar>`（`resources/views/components/avatar.blade.php`）で、未設定なら頭文字と名前で決まる色のアイコンを出す。**各環境で最初に `storage:link` が必要**（ないと画像が表示されない）。`avatar_path` は `$fillable` に入れていない。
- **いいね**: ポストとリプライに付けられる（1人が同じものに1回だけ）。テーブルは `post_likes` と `reply_likes` の 2 つで、どちらも「対象の id + `user_id`」の組み合わせが主キー、両方とも `cascadeOnDelete`（ポストを消すとリプライ経由でリプライのいいねも消えるので、後始末のコードは要らない。1 つの polymorphic テーブルにしなかったのはそのため）。モデルは作らず、`Post` と `Reply` の `likedBy()`（多対多）で操作する。操作は `POST` / `DELETE`（`posts.like.store` / `posts.like.destroy`、`replies.like.*`。**URL は同じでメソッドだけ違う**）で、`PostLikeController` と `ReplyLikeController`。付けるときも取り消すときも、すでにその状態でもエラーにしない。押しても画面は遷移しない: `<x-like-button>` の JavaScript（`@once`。1 ページに 1 回）がフォームの送信を横取りして `fetch` で送り、サーバー（`RespondsToLike`）が返した**ボタン自身の HTML の断片**（`$request->ajax()` のとき。`:assets="false"` でスタイルと JavaScript は含めない）で今のボタンを置き換える。JavaScript が使えないときは、押した画面に `#post-ID` / `#reply-ID` つきで戻る（従来のフォーム送信）。未ログインで裏から送ると 401（`fetch` の既定の `Accept: */*` のとき）で、JavaScript がログイン画面へ移動する。一覧の取得時は `withLikeState($viewer)`（`App\Models\Concerns\HasLikes` のスコープ）で件数 `likes_count` と `liked_by_me` をまとめて取り、ポストごとにクエリが増えないようにしている。表示は `<x-like-button>`（灰色の枠のハート / 押すとピンクの塗りつぶし。0 件は件数を出さない）。
- **認可**: `PostPolicy` と `ReplyPolicy` は `AuthServiceProvider::$policies` に登録されておらず、Laravel の自動検出でそれぞれ `Post` と `Reply` に紐づく。どちらも「書いた本人だけ」（`$user->id === $model->user_id`）で、`PostPolicy` は編集・削除、`ReplyPolicy` は削除のみ。コントローラーで `$this->authorize()` を呼び、本人以外は 403。ビュー側でも `@can` で編集・削除ボタンを出し分けている。
- **文字数の数え方**: ポストの本文、リプライの本文、自己紹介は、ブラウザが改行を `\r\n`（2 文字）で送るため、検証の前に `\n` に正規化してから数える（全角も 1 文字）。このアプリには日本語の言語ファイルがないので、`required` などのメッセージはコントローラーで日本語を明示している。
- **データ**: `users` → `posts` ← `categories`（`posts` が `user_id` と `category_id` を持つ）、`posts` → `replies` ← `users`（`replies` が `post_id` と `user_id` を持つ）、`post_likes` / `reply_likes`（いいね。ポストまたはリプライと、ユーザーを結ぶ中間テーブル）。`users` には `bio`（自己紹介）と `avatar_path`（アバター画像のパス）がある。シーダー（`DatabaseSeeder`）はカテゴリ 3 件、ユーザー 7 人（全員パスワードは `password`。検証用は `usera@example.com` / `userb@example.com`）、ポスト 25 件を作る。リプライとアバター画像は作らない。ファクトリは `UserFactory` のみで、テストでは `Post` などを `Model::create()` で作っている。
- **テスト**: `tests/Feature/` に機能ごとのテストがある（`PostContentLimitTest`、`PostCreateTest`、`LikeTest`、`ReplyTest`、`ProfileTest`、`AvatarTest`、`LoginRedirectTest`）。いずれも `RefreshDatabase` を使う。アバターのテストは `Storage::fake('public')` で、実際のファイルは作らない。

## `docs/` フォルダ

機能ごとの設計書（Issue に対応）。`reply.md`（リプライ、#1）、`profile.md`（プロフィール、#2）、`avatar.md`（アバター画像、#3）、`post-create.md`（新規投稿、#8）、`like.md`（いいね、#9）。見出しは、概要 / 受け入れ条件 / 変えないもの / 増えるテーブル（Mermaid の `erDiagram` つき）/ 増える画面 / 入力チェックとメッセージ一覧 / テスト観点。機能を変えるときは、設計書も合わせて更新する。

## `answers/` フォルダ

Tutorial 13（設計書の書き方）の解答例（Markdown と draw.io の図）で、アプリのコードとは無関係。アプリの機能追加・修正時には読む必要も変更する必要もない。受講者自身の設計書は `docs/` に置く。
