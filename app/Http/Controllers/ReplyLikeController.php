<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsToLike;
use App\Models\Reply;
use Illuminate\Http\Request;

class ReplyLikeController extends Controller
{
    use RespondsToLike;

    /** いいねする。すでに付いていても、エラーにせず何もしない（二重クリックしても結果は同じ） */
    public function store(Request $request, Reply $reply)
    {
        $reply->likedBy()->syncWithoutDetaching([$request->user()->id]);

        return $this->likeResponse($request, $reply, 'reply', route('posts.show', $reply->post_id));
    }

    /** いいねを取り消す。付いていなくても、エラーにしない。消せるのは自分のいいねだけ */
    public function destroy(Request $request, Reply $reply)
    {
        $reply->likedBy()->detach($request->user()->id);

        return $this->likeResponse($request, $reply, 'reply', route('posts.show', $reply->post_id));
    }
}
