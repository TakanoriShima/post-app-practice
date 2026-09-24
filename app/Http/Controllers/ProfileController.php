<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AvatarStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request, User $user)
    {
        $user->loadCount(['posts', 'replies']);

        // タブは ?tab=replies で切り替える。それ以外はポスト
        $tab = $request->query('tab') === 'replies' ? 'replies' : 'posts';

        // 新しい順（同時刻なら id の新しい順）。表示するタブの分だけ取得する
        if ($tab === 'replies') {
            $replies = $user->replies()->with(['user', 'post'])->withLikeState($request->user())->latest()->orderByDesc('id')->get();
            $posts = collect();
        } else {
            $posts = $user->posts()->with(['user', 'category'])->withCount('replies')->withLikeState($request->user())->latest()->orderByDesc('id')->get();
            $replies = collect();
        }

        return view('users.show', compact('user', 'tab', 'posts', 'replies'));
    }

    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request, AvatarStorage $avatars)
    {
        // 編集できるのは常にログイン中の本人だけ（ルートに {user} を持たせない）
        $user = $request->user();

        // ブラウザは改行を \r\n（2文字）で送るので、画面の文字数表示と揃えるため \n に正規化する
        if (is_string($request->input('bio'))) {
            $request->merge(['bio' => str_replace("\r\n", "\n", $request->input('bio'))]);
        }

        $imageMessage = 'アバターには JPEG、PNG、WebP、GIF の画像を選んでください。';

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:140'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120', 'dimensions:max_width=5000,max_height=5000'],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [
            'name.required' => '名前を入力してください。',
            'name.max' => '名前は255字以内で入力してください。',
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式で入力してください。',
            'email.max' => 'メールアドレスは255字以内で入力してください。',
            'email.unique' => 'このメールアドレスはすでに使われています。',
            'bio.max' => '自己紹介は140字以内で入力してください。',
            'avatar.uploaded' => '画像のアップロードに失敗しました。もう一度お試しください。',
            'avatar.file' => $imageMessage,
            'avatar.image' => $imageMessage,
            'avatar.mimes' => $imageMessage,
            'avatar.max' => 'アバター画像は5MB以内にしてください。',
            'avatar.dimensions' => 'アバター画像の縦横は5000px以内にしてください。',
        ]);

        // 更新できるのは名前・メール・自己紹介とアバターだけ（id や password などが送られても無視する）
        $user->fill(Arr::only($validated, ['name', 'email', 'bio']));

        $oldPath = $user->avatar_path;
        $newPath = null;

        if ($request->hasFile('avatar')) {
            // 新しい画像を優先する（「削除」と同時に送られても、新しい画像に差し替える）
            try {
                $newPath = $avatars->store($request->file('avatar'));
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['avatar' => $imageMessage]);
            }
            $user->avatar_path = $newPath;
        } elseif ($request->boolean('remove_avatar')) {
            $user->avatar_path = null;
        }

        try {
            $user->save();
        } catch (Throwable $e) {
            // 保存に失敗したら、新しく置いたファイルを消して元に戻す
            $avatars->delete($newPath);

            throw $e;
        }

        // DB の更新に成功してから古いファイルを消す
        if ($oldPath !== $user->avatar_path) {
            $avatars->delete($oldPath);
        }

        return redirect()->route('users.show', $user);
    }
}
