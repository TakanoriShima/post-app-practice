<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * いいねを受けるモデル（Post、Reply）に付ける。
 * 使うモデルは、いいねをした人との多対多の関係 likedBy() を定義すること。
 */
trait HasLikes
{
    /**
     * 一覧の取得時に、いいねの件数（likes_count）と、$viewer が押したか（liked_by_me）をまとめて取る。
     * ポストやリプライごとに数えるクエリが増えない。
     */
    public function scopeWithLikeState(Builder $query, ?User $viewer): Builder
    {
        return $query
            ->withCount('likedBy as likes_count')
            ->withExists(['likedBy as liked_by_me' => fn ($likes) => $likes->whereKey($viewer?->id)]);
    }

    /** すでに取得したモデルに、いいねの件数と、$viewer が押したかを足す */
    public function loadLikeState(?User $viewer): static
    {
        return $this
            ->loadCount('likedBy as likes_count')
            ->loadExists(['likedBy as liked_by_me' => fn ($likes) => $likes->whereKey($viewer?->id)]);
    }
}
