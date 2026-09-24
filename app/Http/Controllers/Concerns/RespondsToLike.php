<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Post;
use App\Models\Reply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

trait RespondsToLike
{
    /**
     * いいね・取り消しのあとの応答。
     * 裏からの送信（fetch。X-Requested-With つき）には、ハートのボタンだけを描画した HTML の断片を返す
     * （画面は遷移しない。JavaScript が今のボタンを置き換える）。
     * 普通のフォーム送信（JavaScript が使えない場合）には、押した画面へのリダイレクトを返す。
     */
    private function likeResponse(Request $request, Post|Reply $model, string $type, string $fallbackUrl)
    {
        if ($request->ajax()) {
            // 最新の件数と、見ている人が押したかを読み直して、部品を描画する（スタイルと JavaScript は出さない）
            $model->loadLikeState($request->user());

            return response(
                Blade::render('<x-like-button :model="$model" :type="$type" :assets="false" />', compact('model', 'type'))
            );
        }

        $anchor = ($type === 'reply' ? 'reply-' : 'post-').$model->id;

        return back(fallback: $fallbackUrl)->withFragment($anchor);
    }
}
