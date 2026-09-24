<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $post->title }} / つぶやき投稿アプリ</title>
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
        .post { display: flex; gap: 0.75rem; padding: 0.9rem 1rem; border-bottom: 1px solid #EFF1F4; }
        .post.main { border-bottom: 1px solid #D3D9DE; }
        .post-body { flex: 1; min-width: 0; }
        .post-head { display: flex; align-items: baseline; gap: 0.3rem; flex-wrap: wrap; }
        .post-head .name { font-weight: 700; font-size: 0.94rem; }
        .name-link:hover .name { text-decoration: underline; text-underline-offset: 3px; }
        .post-head .time { color: #5B6570; font-size: 0.82rem; font-variant-numeric: tabular-nums; }
        .post-head .time::before { content: "・"; margin-right: 0.05rem; color: #98A1A8; }
        .topic { margin-left: auto; border-radius: 999px; padding: 0.1rem 0.6rem; font-size: 0.7rem; font-weight: 700; }
        .post-title { font-weight: 700; font-size: 0.96rem; margin: 0.28rem 0 0.06rem; }
        .post-text { font-size: 0.94rem; line-height: 1.7; color: #0F1419; overflow-wrap: anywhere; white-space: pre-line; }
        .post-actions { display: flex; gap: 1.2rem; margin-top: 0.5rem; }
        .post-actions button { background: none; border: none; padding: 0; color: #5B6570; font-size: 0.8rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .post-actions button:hover { color: #D6402C; text-decoration: underline; text-underline-offset: 3px; }
        .post-actions button:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; border-radius: 4px; }
        .section-title { padding: 0.7rem 1rem 0.5rem; font-weight: 800; font-size: 0.9rem; color: #5B6570; }
        .empty { color: #5B6570; text-align: center; padding: 2rem 1rem; font-size: 0.9rem; }
        form.reply-form { padding: 1rem 1rem 1.6rem; }
        label { display: block; color: #5B6570; margin-bottom: 0.4rem; font-size: 0.8rem; font-weight: 700; }
        textarea { width: 100%; padding: 0.72rem 0.85rem; border: 1px solid #D3D9DE; border-radius: 10px; font-size: 0.95rem; font-family: inherit; background: #fff; color: #0F1419; min-height: 90px; resize: vertical; line-height: 1.7; }
        textarea:focus { outline: none; border-color: #0F1419; box-shadow: 0 0 0 3px rgba(232, 121, 43, 0.18); }
        .btn { margin-top: 0.8rem; padding: 0.66rem 1.7rem; border-radius: 999px; font-size: 0.9rem; font-weight: 700; cursor: pointer; text-align: center; font-family: inherit; background: #0F1419; color: #fff; border: none; }
        .btn:hover { background: #272C30; }
        .btn:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; }
        .error { color: #D6402C; font-size: 0.83rem; margin-top: 0.3rem; }
    </style>
</head>
<body>
    @php
        $topicTone = [
            'お知らせ' => ['#FFF3DC', '#8A5714'],
            '技術メモ' => ['#E8F1FD', '#2F5AA8'],
            '雑記' => ['#F0F1F3', '#57606A'],
        ];
        [$tBg, $tFg] = $topicTone[$post->category->name] ?? ['#F0F1F3', '#57606A'];
    @endphp
    <div class="col">
        <header class="chrome">
            <div class="chrome-row">
                <a href="{{ route('posts.index') }}" class="brand"><span class="mark">つ</span>つぶやき投稿アプリ</a>
            </div>
            <div class="page-title">ポスト</div>
        </header>

        <main>
            <article class="post main" id="post-{{ $post->id }}">
                <x-avatar :user="$post->user" :size="44" :link="true" />
                <div class="post-body">
                    <div class="post-head">
                        <a href="{{ route('users.show', $post->user) }}" class="name-link"><span class="name">{{ $post->user->name }}</span></a>
                        <span class="time">{{ $post->created_at->format('n月j日 H:i') }}</span>
                        <span class="topic" style="background: {{ $tBg }}; color: {{ $tFg }};">{{ $post->category->name }}</span>
                    </div>
                    <p class="post-title">{{ $post->title }}</p>
                    <p class="post-text">{{ $post->content }}</p>
                    <div class="post-footer">
                        <x-like-button :model="$post" type="post" />
                    </div>
                </div>
            </article>

            <div class="section-title">リプライ</div>

            @if ($replies->isEmpty())
                <p class="empty">まだリプライがありません。</p>
            @else
                @foreach ($replies as $reply)
                    <article class="post" id="reply-{{ $reply->id }}">
                        <x-avatar :user="$reply->user" :size="44" :link="true" />
                        <div class="post-body">
                            <div class="post-head">
                                <a href="{{ route('users.show', $reply->user) }}" class="name-link"><span class="name">{{ $reply->user->name }}</span></a>
                                <span class="time">{{ $reply->created_at->format('n月j日 H:i') }}</span>
                            </div>
                            <p class="post-text">{{ $reply->content }}</p>
                            <div class="post-footer">
                                <x-like-button :model="$reply" type="reply" />
                                @can('delete', $reply)
                                    <div class="post-actions">
                                        <form action="{{ route('replies.destroy', $reply) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">削除</button>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    </article>
                @endforeach
            @endif

            {{-- 空のまま送ったときにサーバー側のエラーを見せるため、textarea に required は付けない --}}
            <form class="reply-form" action="{{ route('replies.store', $post) }}" method="POST">
                @csrf
                <label for="content">リプライを書く</label>
                <textarea id="content" name="content">{{ old('content') }}</textarea>
                @error('content')
                    <p class="error">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn">返信する</button>
            </form>
        </main>
    </div>
</body>
</html>
