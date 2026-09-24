{{--
    新規投稿と編集で共通のフォーム。
    $action: 送信先 / $method: 'POST' か 'PUT' / $post: 編集のときだけ渡す（新規は null）
    $categories: トピック一覧 / $submitLabel: 送信ボタンの文言 / $cancelUrl: キャンセルの戻り先
    サーバー側の入力チェックのメッセージ（日本語）を見せるため、required は付けない。
--}}
<form class="editor" action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-group">
        <label for="title">タイトル</label>
        <input type="text" id="title" name="title" value="{{ old('title', $post?->title) }}">
        @error('title')
            <p class="error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="category_id">トピック</label>
        <select id="category_id" name="category_id">
            @if ($post === null)
                <option value="">選択してください</option>
            @endif
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $post?->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')
            <p class="error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="content">本文</label>
        <textarea id="content" name="content">{{ old('content', $post?->content) }}</textarea>
        @php($remaining = 140 - mb_strlen(str_replace("\r\n", "\n", old('content', $post?->content ?? ''))))
        <p class="counter @if ($remaining < 0) over @endif">残り <span id="content-remaining">{{ $remaining }}</span> 字</p>
        @error('content')
            <p class="error">{{ $message }}</p>
        @enderror
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ $cancelUrl }}" class="btn btn-secondary">キャンセル</a>
    </div>
</form>

<script>
    const form = document.querySelector('form.editor');
    const submitButton = form.querySelector('button[type="submit"]');
    const content = document.getElementById('content');
    const remaining = document.getElementById('content-remaining');
    const counter = remaining.parentElement;

    content.addEventListener('input', () => {
        // Array.from でサロゲートペアも 1 文字として数える（サーバーの mb_strlen と揃える）
        const left = 140 - Array.from(content.value).length;
        remaining.textContent = left;
        counter.classList.toggle('over', left < 0);
    });

    // 二重送信の対策: 送信したらボタンを無効にする（ブラウザの「戻る」で戻ってきたときは有効に戻す）
    form.addEventListener('submit', () => {
        submitButton.disabled = true;
    });
    window.addEventListener('pageshow', () => {
        submitButton.disabled = false;
    });
</script>
