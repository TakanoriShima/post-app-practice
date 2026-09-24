<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reply;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    public function store(Request $request, Post $post)
    {
        // ブラウザは改行を \r\n（2文字）で送るので、ポストの更新と同じく \n に正規化してから数える
        if (is_string($request->input('content'))) {
            $request->merge(['content' => str_replace("\r\n", "\n", $request->input('content'))]);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:140',
        ], [
            'content.required' => '本文を入力してください。',
            'content.max' => '本文は140字以内で入力してください。',
        ]);

        $post->replies()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        return redirect()->route('posts.show', $post);
    }

    public function destroy(Reply $reply)
    {
        $this->authorize('delete', $reply);

        $reply->delete();

        return redirect()->route('posts.show', $reply->post_id);
    }
}
