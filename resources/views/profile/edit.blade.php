<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プロフィールを編集 / つぶやき投稿アプリ</title>
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
        form.editor { padding: 1.2rem 1rem 1.6rem; }
        .form-group { margin-bottom: 1.15rem; }
        label { display: block; color: #5B6570; margin-bottom: 0.4rem; font-size: 0.8rem; font-weight: 700; }
        input[type="text"], input[type="email"], textarea { width: 100%; padding: 0.72rem 0.85rem; border: 1px solid #D3D9DE; border-radius: 10px; font-size: 0.95rem; font-family: inherit; background: #fff; color: #0F1419; }
        textarea { min-height: 120px; resize: vertical; line-height: 1.7; }
        input:focus, textarea:focus { outline: none; border-color: #0F1419; box-shadow: 0 0 0 3px rgba(232, 121, 43, 0.18); }
        .actions { display: flex; gap: 0.7rem; margin-top: 1.4rem; }
        .btn { padding: 0.66rem 1.7rem; border-radius: 999px; font-size: 0.9rem; font-weight: 700; cursor: pointer; text-align: center; font-family: inherit; }
        .btn-primary { background: #0F1419; color: #fff; border: none; }
        .btn-primary:hover { background: #272C30; }
        .btn-secondary { background: #FFFFFF; color: #0F1419; border: 1px solid #D3D9DE; }
        .btn-secondary:hover { background: #F7F8F9; }
        .btn:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; }
        .error { color: #D6402C; font-size: 0.83rem; margin-top: 0.3rem; }
        .avatar-row { display: flex; gap: 1rem; align-items: center; }
        .avatar-fields { flex: 1; min-width: 0; }
        input[type="file"] { width: 100%; font-size: 0.85rem; font-family: inherit; }
        .hint { color: #5B6570; font-size: 0.78rem; margin-top: 0.4rem; line-height: 1.5; }
        label.check { display: flex; align-items: center; gap: 0.4rem; margin: 0.6rem 0 0; font-weight: 600; cursor: pointer; }
        .counter { color: #5B6570; font-size: 0.8rem; margin-top: 0.3rem; text-align: right; }
        .counter.over { color: #D6402C; font-weight: 700; }
    </style>
</head>
<body>
    <div class="col">
        <header class="chrome">
            <div class="chrome-row">
                <a href="{{ route('posts.index') }}" class="brand"><span class="mark">つ</span>つぶやき投稿アプリ</a>
            </div>
            <div class="page-title">プロフィールを編集</div>
        </header>

        <main>
            {{-- 名前やメールが空のまま送ったときにサーバー側のエラーを見せるため、required は付けない --}}
            <form class="editor" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="avatar">アバター画像</label>
                    <div class="avatar-row">
                        <div id="avatar-preview">
                            <x-avatar :user="$user" :size="96" />
                        </div>
                        <div class="avatar-fields">
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
                            <p class="hint">JPEG・PNG・WebP・GIF、5MB以内。中央が正方形に切り抜かれます。</p>
                            @if (filled($user->avatar_path))
                                <label class="check" for="remove_avatar">
                                    <input type="checkbox" id="remove_avatar" name="remove_avatar" value="1" @checked(old('remove_avatar'))>
                                    画像を削除して初期アイコンに戻す
                                </label>
                            @endif
                        </div>
                    </div>
                    @error('avatar')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="name">名前</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}">
                    @error('name')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email">メールアドレス</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}">
                    @error('email')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="bio">自己紹介</label>
                    <textarea id="bio" name="bio">{{ old('bio', $user->bio) }}</textarea>
                    @php($remaining = 140 - mb_strlen(str_replace("\r\n", "\n", old('bio', $user->bio ?? ''))))
                    <p class="counter @if ($remaining < 0) over @endif">残り <span id="bio-remaining">{{ $remaining }}</span> 字</p>
                    @error('bio')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">更新</button>
                    <a href="{{ route('users.show', $user) }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </main>
    </div>

    <script>
        // 選んだ画像を、その場で丸くプレビューする
        const avatarInput = document.getElementById('avatar');
        const avatarPreview = document.getElementById('avatar-preview');

        avatarInput.addEventListener('change', () => {
            const file = avatarInput.files[0];
            if (!file) {
                return;
            }
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = '選択した画像のプレビュー';
            img.style.cssText = 'width: 96px; height: 96px; border-radius: 999px; object-fit: cover; display: block;';
            avatarPreview.replaceChildren(img);
        });

        const bio = document.getElementById('bio');
        const remaining = document.getElementById('bio-remaining');
        const counter = remaining.parentElement;

        bio.addEventListener('input', () => {
            // Array.from でサロゲートペアも 1 文字として数える（サーバーの mb_strlen と揃える）
            const left = 140 - Array.from(bio.value).length;
            remaining.textContent = left;
            counter.classList.toggle('over', left < 0);
        });
    </script>
</body>
</html>
