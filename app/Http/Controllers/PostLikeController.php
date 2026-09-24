<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsToLike;
use App\Models\Post;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    use RespondsToLike;

    /** いいねする。すでに付いていても、エラーにせず何もしない（二重クリックしても結果は同じ） */
    public function store(Request $request, Post $post)
    {
        $post->likedBy()->syncWithoutDetaching([$request->user()->id]);

        return $this->likeResponse($request, $post, 'post', route('posts.index'));
    }

    /** いいねを取り消す。付いていなくても、エラーにしない。消せるのは自分のいいねだけ */
    public function destroy(Request $request, Post $post)
    {
        $post->likedBy()->detach($request->user()->id);

        return $this->likeResponse($request, $post, 'post', route('posts.index'));
    }
}
