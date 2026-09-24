<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $other;

    private Category $category;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['name' => 'ポスト投稿者']);
        $this->other = User::factory()->create(['name' => '別のユーザー']);
        $this->category = Category::create(['name' => '雑記']);
        $this->post = $this->makePost('ポストのタイトル', 'ポストの本文');
    }

    private function makePost(string $title, string $content): Post
    {
        return Post::create([
            'user_id' => $this->author->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'content' => $content,
        ]);
    }

    private function makeReply(User $user, string $content, ?Post $post = null, ?string $createdAt = null): Reply
    {
        $reply = Reply::create([
            'post_id' => ($post ?? $this->post)->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        if ($createdAt !== null) {
            $reply->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $reply;
    }

    private function sendReply(string $content, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->other)
            ->from(route('posts.show', $this->post))
            ->post(route('replies.store', $this->post), ['content' => $content]);
    }

    // ---- 詳細ページ ----

    public function test_ポストを開くとポストとリプライが見える(): void
    {
        $this->makeReply($this->other, '最初のリプライ');

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('ポストのタイトル')
            ->assertSee('ポストの本文')
            ->assertSee('最初のリプライ');
    }

    public function test_別のポストのリプライは見えない(): void
    {
        $otherPost = $this->makePost('別のポスト', '別の本文');
        $this->makeReply($this->other, 'こちらのリプライ');
        $this->makeReply($this->other, '別ポストのリプライ', $otherPost);

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertSee('こちらのリプライ')
            ->assertDontSee('別ポストのリプライ');
    }

    public function test_リプライは古い順に並ぶ(): void
    {
        $this->makeReply($this->other, '二番目のリプライ', null, '2024-01-01 10:00:00');
        $this->makeReply($this->author, '三番目のリプライ', null, '2024-01-01 11:00:00');
        $this->makeReply($this->other, '一番目のリプライ', null, '2024-01-01 09:00:00');

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertSeeInOrder(['一番目のリプライ', '二番目のリプライ', '三番目のリプライ']);
    }

    public function test_リプライがないときはその旨が表示される(): void
    {
        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('まだリプライがありません。');
    }

    public function test_未ログインだと詳細ページはログイン画面へリダイレクトされる(): void
    {
        $this->get(route('posts.show', $this->post))->assertRedirect('/login');
    }

    public function test_存在しないポストは404になる(): void
    {
        $this->actingAs($this->other)->get('/posts/9999')->assertNotFound();
    }

    // ---- リプライ送信 ----

    public function test_本文を入れて送るとリプライが増えて一覧に出る(): void
    {
        $this->sendReply('はじめてのリプライ')
            ->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseHas('replies', [
            'post_id' => $this->post->id,
            'user_id' => $this->other->id,
            'content' => 'はじめてのリプライ',
        ]);

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertSee('はじめてのリプライ');
    }

    public function test_本文が空だとエラーが表示されてリプライは増えない(): void
    {
        $this->sendReply('')
            ->assertRedirect(route('posts.show', $this->post))
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('replies', 0);
    }

    public function test_空のエラーメッセージは日本語で表示される(): void
    {
        $this->followRedirects($this->sendReply(''))
            ->assertSee('本文を入力してください。');
    }

    public function test_空白だけの本文でもエラーになりリプライは増えない(): void
    {
        $this->sendReply("  \n  ")->assertSessionHasErrors('content');

        $this->assertDatabaseCount('replies', 0);
    }

    public function test_140字ちょうどの本文は送れる(): void
    {
        $content = str_repeat('a', 140);

        $this->sendReply($content)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('replies', ['content' => $content]);
    }

    public function test_全角140字の本文は送れる(): void
    {
        $content = str_repeat('あ', 140);

        $this->sendReply($content)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('replies', ['content' => $content]);
    }

    public function test_改行を含む140字の本文は送れる(): void
    {
        // ブラウザは改行を \r\n で送る。画面上は 140 字（改行は 1 字）
        $this->sendReply(str_repeat('a', 69)."\r\n".str_repeat('a', 70))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('replies', ['content' => str_repeat('a', 69)."\n".str_repeat('a', 70)]);
    }

    public function test_141字の本文はエラーになり増えない(): void
    {
        $this->sendReply(str_repeat('あ', 141))
            ->assertRedirect(route('posts.show', $this->post))
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('replies', 0);
    }

    public function test_141字のエラーメッセージは日本語で表示される(): void
    {
        $this->followRedirects($this->sendReply(str_repeat('あ', 141)))
            ->assertSee('本文は140字以内で入力してください。');
    }

    public function test_エラーのとき入力した本文が残る(): void
    {
        $content = str_repeat('あ', 141);

        $this->followRedirects($this->sendReply($content))
            ->assertSee($content);
    }

    public function test_未ログインではリプライを送れない(): void
    {
        $this->post(route('replies.store', $this->post), ['content' => 'こんにちは'])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('replies', 0);
    }

    // ---- リプライ削除 ----

    public function test_自分のリプライは消せる(): void
    {
        $reply = $this->makeReply($this->other, '消したいリプライ');

        $this->actingAs($this->other)
            ->delete(route('replies.destroy', $reply))
            ->assertRedirect(route('posts.show', $this->post));

        $this->assertModelMissing($reply);
    }

    public function test_他人のリプライを消そうとすると403になり消えない(): void
    {
        $reply = $this->makeReply($this->author, '他人のリプライ');

        $this->actingAs($this->other)
            ->delete(route('replies.destroy', $reply))
            ->assertForbidden();

        $this->assertModelExists($reply);
    }

    public function test_ポストの投稿者でも他人のリプライは消せない(): void
    {
        $reply = $this->makeReply($this->other, '他人のリプライ');

        $this->actingAs($this->author)
            ->delete(route('replies.destroy', $reply))
            ->assertForbidden();

        $this->assertModelExists($reply);
    }

    public function test_削除ボタンは自分のリプライにだけ出る(): void
    {
        $mine = $this->makeReply($this->other, '自分のリプライ');
        $theirs = $this->makeReply($this->author, '他人のリプライ');

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertSee(route('replies.destroy', $mine), false)
            ->assertDontSee(route('replies.destroy', $theirs), false);
    }

    public function test_未ログインではリプライを消せない(): void
    {
        $reply = $this->makeReply($this->other, 'リプライ');

        $this->delete(route('replies.destroy', $reply))->assertRedirect('/login');

        $this->assertModelExists($reply);
    }

    // ---- 既存の動きが変わらないこと ----

    public function test_タイムラインのタイトルが詳細ページへのリンクになっている(): void
    {
        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('ポストのタイトル')
            ->assertSee(route('posts.show', $this->post), false);
    }

    public function test_タイムラインのタイトルの横にリプライ件数が表示される(): void
    {
        $this->makeReply($this->other, '一件目');
        $this->makeReply($this->author, '二件目');

        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertSee('<span class="reply-count">(2)</span>', false);
    }

    public function test_リプライがないポストは0件と表示される(): void
    {
        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertSee('<span class="reply-count">(0)</span>', false);
    }

    public function test_リプライ件数はポストごとに数えられる(): void
    {
        $otherPost = $this->makePost('別のポスト', '別の本文');
        $this->makeReply($this->other, '一件目');
        $this->makeReply($this->other, '二件目');
        $this->makeReply($this->other, '別ポストへ', $otherPost);

        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertSee('別のポスト</a> <span class="reply-count">(1)</span>', false)
            ->assertSee('ポストのタイトル</a> <span class="reply-count">(2)</span>', false);
    }

    public function test_リプライが付いたポストも消せてリプライも一緒に消える(): void
    {
        $reply = $this->makeReply($this->other, 'ポストと一緒に消える');

        $this->actingAs($this->author)
            ->delete(route('posts.destroy', $this->post))
            ->assertRedirect(route('posts.index'));

        $this->assertModelMissing($this->post);
        $this->assertModelMissing($reply);
    }
}
