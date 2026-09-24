@php
    $topicTone = [
        'お知らせ' => ['#FFF3DC', '#8A5714'],
        '技術メモ' => ['#E8F1FD', '#2F5AA8'],
        '雑記' => ['#F0F1F3', '#57606A'],
    ];
    [$tBg, $tFg] = $topicTone[$post->category->name] ?? ['#F0F1F3', '#57606A'];
@endphp
<article class="post" id="post-{{ $post->id }}">
    <x-avatar :user="$post->user" :size="44" :link="true" />
    <div class="post-body">
        <div class="post-head">
            <a href="{{ route('users.show', $post->user) }}" class="name-link"><span class="name">{{ $post->user->name }}</span></a>
            <span class="time">{{ $post->created_at->format('n月j日 H:i') }}</span>
            <span class="topic" style="background: {{ $tBg }}; color: {{ $tFg }};">{{ $post->category->name }}</span>
        </div>
        <p class="post-title"><a href="{{ route('posts.show', $post) }}">{{ $post->title }}</a> <span class="reply-count">({{ $post->replies_count }})</span></p>
        <p class="post-text">{{ $post->content }}</p>
        <div class="post-footer">
            <x-like-button :model="$post" type="post" />
            @canany(['update', 'delete'], $post)
                <div class="post-actions">
                    @can('update', $post)
                        <a href="{{ route('posts.edit', $post) }}">編集</a>
                    @endcan
                    @can('delete', $post)
                        <form action="{{ route('posts.destroy', $post) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit">削除</button>
                        </form>
                    @endcan
                </div>
            @endcanany
        </div>
    </div>
</article>
