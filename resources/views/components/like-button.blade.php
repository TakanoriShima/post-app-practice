{{--
    いいねのボタン。$model は Post か Reply で、一覧の取得時に withLikeState() で
    likes_count（件数）と liked_by_me（自分が押したか）を取っておくこと。$type は 'post' か 'reply'。
    押していない: 灰色の枠のハート / 押した: ピンクの塗りつぶしのハート（色だけでなく形でも区別する）

    押しても画面は遷移しない: 下の JavaScript が送信を横取りして裏で送り、サーバーが返した
    このボタン自身の HTML の断片で、今のボタンを置き換える。JavaScript が使えないときは、
    普通のフォーム送信（押した画面へリダイレクト）になる。
    $assets: スタイルと JavaScript を出すか。サーバーが断片を返すときは false（ページに1回だけ出せばよい）
--}}
@props(['model', 'type' => 'post', 'assets' => true])
@php
    $liked = (bool) $model->liked_by_me;
    $count = (int) $model->likes_count;
    $base = $type === 'reply' ? 'replies' : 'posts';
    $action = route($base.'.like.'.($liked ? 'destroy' : 'store'), $model);
    $label = $liked ? 'いいねを取り消す' : 'いいねする';
@endphp
@if ($assets)
    @once
        <style>
            .post-footer { display: flex; align-items: center; gap: 1.2rem; margin-top: 0.35rem; }
            .post-footer .post-actions { margin-top: 0; }
            .like-form { display: inline-flex; align-items: center; }
            .like-form[aria-busy="true"] .like-btn { opacity: 0.5; pointer-events: none; }
            .like-btn { display: inline-flex; align-items: center; gap: 0.2rem; min-height: 32px; margin-left: -0.5rem; padding: 0 0.6rem 0 0.5rem; border: none; border-radius: 999px; background: none; color: #7A8590; font-family: inherit; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
            .like-btn svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linejoin: round; flex-shrink: 0; }
            .like-btn .like-count { min-width: 1ch; color: #5B6570; font-variant-numeric: tabular-nums; }
            .like-btn:hover { color: #F91880; background: rgba(249, 24, 128, 0.1); }
            .like-btn:hover .like-count { color: #C0146B; }
            .like-btn.liked { color: #F91880; }
            .like-btn.liked svg { fill: currentColor; }
            .like-btn.liked .like-count { color: #C0146B; }
            .like-btn:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; }
            .like-error { margin-left: 0.3rem; color: #D6402C; font-size: 0.78rem; }
            /* 押した瞬間だけ、ハートが少し大きくなって戻る。動きが苦手な人向けの設定では動かさない */
            @keyframes like-pop { 0% { transform: scale(1); } 40% { transform: scale(1.3); } 100% { transform: scale(1); } }
            .like-pop { animation: like-pop 0.25s ease-out; }
            @media (prefers-reduced-motion: reduce) { .like-pop { animation: none; } }
        </style>
        <script>
            // いいねのボタンを押しても画面を遷移させない。送信を横取りして裏で送り、返ってきた断片で置き換える。
            document.addEventListener('submit', async (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.classList.contains('like-form')) {
                    return;
                }
                event.preventDefault();

                // 押している間は、もう一度押しても送らない（disabled にするとフォーカスが外れるので使わない）
                if (form.getAttribute('aria-busy') === 'true') {
                    return;
                }
                form.setAttribute('aria-busy', 'true');
                form.querySelector('.like-error')?.remove();

                const wasLiked = form.querySelector('.like-btn')?.classList.contains('liked') ?? false;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form), // _token と、取り消しのときの _method=DELETE が入っている
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });

                    if (response.status === 401 || response.redirected) {
                        // ログインが切れている
                        window.location.href = form.dataset.loginUrl;
                        return;
                    }
                    if (response.status === 419) {
                        // セッションの期限切れ。トークンを取り直すため、再読み込みする
                        window.location.reload();
                        return;
                    }
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const template = document.createElement('template');
                    template.innerHTML = (await response.text()).trim();
                    const next = template.content.firstElementChild;
                    if (!next || !next.classList.contains('like-form')) {
                        throw new Error('unexpected response');
                    }

                    // 押した瞬間（押していない → 押した）だけ、ハートを動かす
                    const nextButton = next.querySelector('.like-btn');
                    if (!wasLiked && nextButton.classList.contains('liked')) {
                        nextButton.querySelector('svg')?.classList.add('like-pop');
                    }

                    const hadFocus = form.contains(document.activeElement);
                    form.replaceWith(next);
                    if (hadFocus) {
                        nextButton.focus(); // キーボードや読み上げの操作が途切れないようにする
                    }
                } catch (error) {
                    // 失敗したら、ボタンは元の状態のまま、短いメッセージを数秒だけ出す
                    form.removeAttribute('aria-busy');
                    const message = document.createElement('span');
                    message.className = 'like-error';
                    message.setAttribute('role', 'alert');
                    message.textContent = '通信に失敗しました';
                    form.append(message);
                    setTimeout(() => message.remove(), 3000);
                }
            });
        </script>
    @endonce
@endif
<form class="like-form" action="{{ $action }}" method="POST" data-login-url="{{ route('login') }}">
    @csrf
    @if ($liked)
        @method('DELETE')
    @endif
    <button type="submit" class="{{ $liked ? 'like-btn liked' : 'like-btn' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}" aria-label="{{ $label }}@if ($count > 0)（{{ $count }}件）@endif" title="{{ $label }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        @if ($count > 0)
            <span class="like-count">{{ $count }}</span>
        @endif
    </button>
</form>
