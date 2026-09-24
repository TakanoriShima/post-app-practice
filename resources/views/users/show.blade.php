<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $user->name }}のプロフィール / つぶやき投稿アプリ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Noto Sans JP", sans-serif; background: #FFFFFF; color: #0F1419; font-feature-settings: "palt"; }
        a { text-decoration: none; color: inherit; }
        .col { max-width: 600px; margin: 0 auto; min-height: 100dvh; border-left: 1px solid #EFF1F4; border-right: 1px solid #EFF1F4; }
        .chrome { position: sticky; top: 0; z-index: 10; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid #EFF1F4; }
        .chrome-row { display: flex; align-items: center; padding: 0.6rem 1rem; }
        .brand { display: flex; align-items: center; gap: 0.5rem; font-weight: 800; font-size: 1.05rem; letter-spacing: -0.02em; }
        .brand .mark { width: 28px; height: 28px; border-radius: 38%; background: linear-gradient(135deg, #FFB03A, #FF7A59); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; flex-shrink: 0; }
        .page-title { padding: 0.55rem 1rem 0.7rem; font-weight: 800; font-size: 1.06rem; }
        .profile { display: flex; gap: 1rem; padding: 1.2rem 1rem; border-bottom: 1px solid #EFF1F4; }
        .profile-body { flex: 1; min-width: 0; }
        .profile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.8rem; }
        .profile-name { font-weight: 800; font-size: 1.2rem; overflow-wrap: anywhere; }
        .profile-email { color: #5B6570; font-size: 0.85rem; margin-top: 0.1rem; overflow-wrap: anywhere; }
        .profile-bio { margin-top: 0.7rem; font-size: 0.94rem; line-height: 1.7; overflow-wrap: anywhere; white-space: pre-line; }
        .profile-joined { margin-top: 0.7rem; color: #5B6570; font-size: 0.82rem; }
        .btn-edit { flex-shrink: 0; background: #FFFFFF; color: #0F1419; border: 1px solid #D3D9DE; padding: 0.38rem 1rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .btn-edit:hover { background: #F7F8F9; }
        .btn-edit:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; }
        .tabs { display: flex; border-bottom: 1px solid #EFF1F4; }
        .tab { flex: 1; text-align: center; padding: 0.8rem 0.5rem; font-size: 0.9rem; font-weight: 700; color: #5B6570; border-bottom: 3px solid transparent; }
        .tab:hover { background: #F7F8F9; }
        .tab.active { color: #0F1419; border-bottom-color: #E8792B; }
        .tab:focus-visible { outline: 2px solid #E8792B; outline-offset: -2px; }
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
        .reply-to { color: #5B6570; font-size: 0.82rem; margin-top: 0.25rem; overflow-wrap: anywhere; }
        .reply-to a { font-weight: 700; color: #2F5AA8; }
        .reply-to a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .post-actions { display: flex; gap: 1.2rem; margin-top: 0.5rem; }
        .post-actions a, .post-actions button { background: none; border: none; padding: 0; color: #5B6570; font-size: 0.8rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .post-actions a:hover { color: #0F1419; text-decoration: underline; text-underline-offset: 3px; }
        .post-actions button:hover { color: #D6402C; text-decoration: underline; text-underline-offset: 3px; }
        .post-actions a:focus-visible, .post-actions button:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; border-radius: 4px; }
        .empty { color: #5B6570; text-align: center; padding: 3.5rem 1rem; }
    </style>
</head>
<body>
    @php($isMe = auth()->id() === $user->id)
    <div class="col">
        <header class="chrome">
            <div class="chrome-row">
                <a href="{{ route('posts.index') }}" class="brand"><span class="mark">つ</span>つぶやき投稿アプリ</a>
            </div>
            <div class="page-title">プロフィール</div>
        </header>

        <main>
            <section class="profile">
                <x-avatar :user="$user" :size="96" />
                <div class="profile-body">
                    <div class="profile-head">
                        <div>
                            <p class="profile-name">{{ $user->name }}</p>
                            @if ($isMe)
                                <p class="profile-email">{{ $user->email }}</p>
                            @endif
                        </div>
                        @if ($isMe)
                            <a href="{{ route('profile.edit') }}" class="btn-edit">プロフィールを編集</a>
                        @endif
                    </div>
                    @if (filled($user->bio))
                        <p class="profile-bio">{{ $user->bio }}</p>
                    @endif
                    <p class="profile-joined">{{ $user->created_at->format('Y年n月j日') }}に登録</p>
                </div>
            </section>

            <nav class="tabs">
                <a href="{{ route('users.show', $user) }}" class="tab @if ($tab === 'posts') active @endif">ポスト（{{ $user->posts_count }}）</a>
                <a href="{{ route('users.show', ['user' => $user, 'tab' => 'replies']) }}" class="tab @if ($tab === 'replies') active @endif">リプライ（{{ $user->replies_count }}）</a>
            </nav>

            @if ($tab === 'replies')
                @forelse ($replies as $reply)
                    <article class="post">
                        <x-avatar :user="$reply->user" :size="44" />
                        <div class="post-body">
                            <div class="post-head">
                                <span class="name">{{ $reply->user->name }}</span>
                                <span class="time">{{ $reply->created_at->format('n月j日 H:i') }}</span>
                            </div>
                            <p class="reply-to">「<a href="{{ route('posts.show', $reply->post) }}">{{ $reply->post->title }}</a>」へのリプライ</p>
                            <p class="post-text">{{ $reply->content }}</p>
                        </div>
                    </article>
                @empty
                    <p class="empty">まだリプライがありません。</p>
                @endforelse
            @else
                @forelse ($posts as $post)
                    @include('posts._post')
                @empty
                    <p class="empty">まだポストがありません。</p>
                @endforelse
            @endif
        </main>
    </div>
</body>
</html>
