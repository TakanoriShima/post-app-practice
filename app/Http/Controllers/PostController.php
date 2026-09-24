<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with(['user', 'category'])->withCount('replies')->latest()->get();

        return view('posts.index', compact('posts'));
    }

    public function show(Post $post)
    {
        $post->load(['user', 'category']);

        // 古い順（同時刻なら id 順）
        $replies = $post->replies()->with('user')->orderBy('created_at')->orderBy('id')->get();

        return view('posts.show', compact('post', 'replies'));
    }

    public function create()
    {
        $categories = Category::orderBy('id')->get();

        return view('posts.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);

        // 投稿者はログイン中の本人。user_id は画面から送られた値を使わず、リレーションで決める
        $request->user()->posts()->create($validated);

        return redirect()->route('posts.index');
    }

    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        $categories = Category::orderBy('id')->get();

        return view('posts.edit', compact('post', 'categories'));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $validated = $this->validatePost($request);

        $post->update($validated);

        return redirect()->route('posts.index');
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('posts.index');
    }

    /** 新規投稿（store）と編集（update）で共通の入力チェック */
    private function validatePost(Request $request): array
    {
        // ブラウザは改行を \r\n（2文字）で送るので、画面の文字数表示と揃えるため \n に正規化する
        if (is_string($request->input('content'))) {
            $request->merge(['content' => str_replace("\r\n", "\n", $request->input('content'))]);
        }

        return $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:140',
            'category_id' => 'required|exists:categories,id',
        ], [
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは255字以内で入力してください。',
            'category_id.required' => 'トピックを選んでください。',
            'category_id.exists' => 'トピックを選んでください。',
            'content.required' => '本文を入力してください。',
            'content.max' => '本文は140字以内で入力してください。',
        ]);
    }
}
