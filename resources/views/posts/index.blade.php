<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ホーム / つぶやき投稿アプリ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Noto Sans JP", sans-serif; background: #FFFFFF; color: #0F1419; font-feature-settings: "palt"; }
        a { text-decoration: none; color: inherit; }
        .col { max-width: 600px; margin: 0 auto; min-height: 100dvh; border-left: 1px solid #EFF1F4; border-right: 1px solid #EFF1F4; }
        .chrome { position: sticky; top: 0; z-index: 10; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid #EFF1F4; }
        .chrome-row { display: flex; justify-content: space-between; align-items: center; gap: 0.8rem; padding: 0.6rem 1rem; }
        .brand { display: flex; align-items: center; gap: 0.5rem; font-weight: 800; font-size: 1.05rem; letter-spacing: -0.02em; }
        .brand .mark { width: 28px; height: 28px; border-radius: 38%; background: linear-gradient(135deg, #FFB03A, #FF7A59); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; flex-shrink: 0; }
        .me { display: flex; align-items: center; gap: 0.2rem; flex-shrink: 0; }
        .nav-link { display: inline-flex; align-items: center; gap: 0.4rem; background: none; border: none; padding: 0.35rem 0.7rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; color: #0F1419; cursor: pointer; font-family: inherit; }
        .nav-link:hover { background: #F0F1F3; }
        .nav-primary { background: #0F1419; color: #fff; }
        .nav-primary:hover { background: #272C30; }
        .nav-link:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; }
        .page-title { padding: 0.55rem 1rem 0.7rem; font-weight: 800; font-size: 1.06rem; }
        .post { display: flex; gap: 0.75rem; padding: 0.9rem 1rem; border-bottom: 1px solid #EFF1F4; }
        .post:hover { background: #F7F8F9; }
        .post-body { flex: 1; min-width: 0; }
        .post-head { display: flex; align-items: baseline; gap: 0.3rem; flex-wrap: wrap; }
        .post-head .name { font-weight: 700; font-size: 0.94rem; }
        .name-link:hover .name { text-decoration: underline; text-underline-offset: 3px; }
        .post-head .time { color: #5B6570; font-size: 0.82rem; font-variant-numeric: tabular-nums; }
        .post-head .time::before { content: "・"; margin-right: 0.05rem; color: #98A1A8; }
        .topic { margin-left: auto; border-radius: 999px; padding: 0.1rem 0.6rem; font-size: 0.7rem; font-weight: 700; }
        .post-title { font-weight: 700; font-size: 0.96rem; margin: 0.28rem 0 0.06rem; }
        .post-title a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .post-title .reply-count { color: #5B6570; font-weight: 400; font-size: 0.86rem; font-variant-numeric: tabular-nums; }
        .post-text { font-size: 0.94rem; line-height: 1.7; color: #0F1419; overflow-wrap: anywhere; }
        .post-actions { display: flex; gap: 1.2rem; margin-top: 0.5rem; }
        .post-actions a, .post-actions button { background: none; border: none; padding: 0; color: #5B6570; font-size: 0.8rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .post-actions a:hover { color: #0F1419; text-decoration: underline; text-underline-offset: 3px; }
        .post-actions button:hover { color: #D6402C; text-decoration: underline; text-underline-offset: 3px; }
        .post-actions a:focus-visible, .post-actions button:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; border-radius: 4px; }
        .empty { color: #5B6570; text-align: center; padding: 3.5rem 1rem; }
    </style>
</head>
<body>
    <div class="col">
        <header class="chrome">
            <div class="chrome-row">
                <a href="{{ route('posts.index') }}" class="brand"><span class="mark">つ</span>つぶやき投稿アプリ</a>
                <nav class="me" aria-label="ユーザーメニュー">
                    <a href="{{ route('posts.create') }}" class="nav-link nav-primary">新規投稿</a>
                    <a href="{{ route('users.show', auth()->user()) }}" class="nav-link">
                        <x-avatar :user="auth()->user()" :size="28" />
                        プロフィール
                    </a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="nav-link">ログアウト</button>
                    </form>
                </nav>
            </div>
            <div class="page-title">ホーム</div>
        </header>

        <main class="feed">
            @if($posts->isEmpty())
                <p class="empty">まだポストがありません。</p>
            @else
                @foreach ($posts as $post)
                    @include('posts._post')
                @endforeach
            @endif
        </main>
    </div>
</body>
</html>
