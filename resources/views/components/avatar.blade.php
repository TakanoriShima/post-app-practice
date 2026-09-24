@props(['user', 'size' => 44, 'link' => false])
@php
    // アイコンの色は登録した名前から決まる（crc32 % 18）
    $grad = [
        ['#5B8DEF', '#3F5FD0'],
        ['#E8A23D', '#C97F1B'],
        ['#34B58B', '#1E8A6E'],
        ['#E05252', '#B93245'],
        ['#8E6AE0', '#6748BE'],
        ['#3FB0C9', '#2884A5'],
        ['#F2865C', '#DB5A34'],
        ['#6D7BE0', '#4A55C2'],
        ['#8FAE3E', '#6C8A26'],
        ['#31B183', '#188064'],
        ['#7C93B5', '#56718F'],
        ['#F7A934', '#E8721F'],
        ['#EF6A6A', '#CE3A50'],
        ['#9B6CE8', '#6E48C9'],
        ['#E45FA3', '#C13A85'],
        ['#B58A5C', '#8F653A'],
        ['#55A8E2', '#3380BC'],
        ['#4E9E8E', '#2F7A6B'],
    ];
    [$g1, $g2] = $grad[crc32($user->name) % count($grad)];
    $fontSize = round($size * 0.4, 1);
    $imageUrl = $user->avatarUrl();
@endphp
@if ($link)<a href="{{ route('users.show', $user) }}" aria-label="{{ $user->name }}のプロフィール" style="flex-shrink: 0; display: block;">@endif
@if ($imageUrl)<img class="avatar" src="{{ $imageUrl }}" alt="{{ $user->name }}" @if ($size < 60) loading="lazy" @endif style="width: {{ $size }}px; height: {{ $size }}px; border-radius: 999px; object-fit: cover; flex-shrink: 0; display: block; background: #EFF1F4;">
@else<div class="avatar" style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ $fontSize }}px; border-radius: 999px; flex-shrink: 0; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; background: linear-gradient(135deg, {{ $g1 }}, {{ $g2 }});">{{ mb_substr($user->name, 0, 1) }}</div>@endif
@if ($link)</a>@endif
