<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['name' => 'わたし']);
        $this->other = User::factory()->create(['name' => 'ほかの人']);
        $this->category = Category::create(['name' => '雑記']);
    }

    private function store(array $data = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->me)
            ->from(route('posts.create'))
            ->post(route('posts.store'), array_merge([
                'title' => '新しいタイトル',
                'category_id' => $this->category->id,
                'content' => '新しい本文',
            ], $data));
    }

    // ---- 作成画面 ----

    public function test_作成画面を開ける(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.create'))
            ->assertOk()
            ->assertSee('新規投稿')
            ->assertSee('name="title"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="content"', false)
            ->assertSee(route('posts.store'), false);
    }

    public function test_作成画面は_posts_show_に食われず404にならない(): void
    {
        // /posts/create は /posts/{post} より前に定義してあるので、create が {post} として扱われない
        $this->actingAs($this->me)->get('/posts/create')->assertOk();
    }

    public function test_未ログインだと作成画面はログイン画面へリダイレクトされる(): void
    {
        $this->get(route('posts.create'))->assertRedirect('/login');
    }

    public function test_トピックは選択してくださいが初期状態になる(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.create'))
            ->assertSee('<option value="">選択してください</option>', false)
            ->assertDontSee('selected');
    }

    public function test_作成画面にトピックの一覧が出る(): void
    {
        Category::create(['name' => '技術メモ']);

        $this->actingAs($this->me)
            ->get(route('posts.create'))
            ->assertSee('雑記')
            ->assertSee('技術メモ');
    }

    public function test_作成画面に残り文字数と二重送信の対策が出る(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.create'))
            ->assertSee('<span id="content-remaining">140</span>', false)
            ->assertSee('submitButton.disabled = true', false);
    }

    public function test_作成画面のキャンセルはタイムラインへ戻る(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.create'))
            ->assertSee('href="'.route('posts.index').'" class="btn btn-secondary"', false);
    }

    // ---- 投稿 ----

    public function test_投稿するとポストが1件増えて投稿者は本人になる(): void
    {
        $this->store()->assertRedirect(route('posts.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseHas('posts', [
            'user_id' => $this->me->id,
            'category_id' => $this->category->id,
            'title' => '新しいタイトル',
            'content' => '新しい本文',
        ]);
    }

    public function test_投稿したポストがタイムラインの一番上に出る(): void
    {
        $old = Post::create([
            'user_id' => $this->other->id,
            'category_id' => $this->category->id,
            'title' => '古いポスト',
            'content' => '古い本文',
        ]);
        $old->forceFill(['created_at' => '2024-01-01 09:00:00'])->save();

        $this->store(['title' => '投稿したばかりのポスト']);

        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSeeInOrder(['投稿したばかりのポスト', '古いポスト']);
    }

    public function test_user_idを送っても投稿者は本人のままになる(): void
    {
        $this->store(['user_id' => $this->other->id]);

        $this->assertDatabaseHas('posts', ['title' => '新しいタイトル', 'user_id' => $this->me->id]);
        $this->assertDatabaseMissing('posts', ['user_id' => $this->other->id]);
    }

    public function test_投稿したポストは本人が編集と削除できる(): void
    {
        $this->store();
        $post = Post::firstOrFail();

        $this->actingAs($this->me)->get(route('posts.edit', $post))->assertOk();
        $this->actingAs($this->me)->delete(route('posts.destroy', $post))->assertRedirect(route('posts.index'));
        $this->assertModelMissing($post);
    }

    public function test_未ログインでは投稿できない(): void
    {
        $this->post(route('posts.store'), [
            'title' => 'タイトル',
            'category_id' => $this->category->id,
            'content' => '本文',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('posts', 0);
    }

    // ---- 入力チェック ----

    public function test_タイトルが空だとエラーになり増えない(): void
    {
        $this->followRedirects($this->store(['title' => '']))
            ->assertSee('タイトルを入力してください。');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_空白だけのタイトルでもエラーになる(): void
    {
        $this->store(['title' => '   '])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_タイトルが256字だとエラーになる(): void
    {
        $this->followRedirects($this->store(['title' => str_repeat('あ', 256)]))
            ->assertSee('タイトルは255字以内で入力してください。');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_タイトルが255字ちょうどなら投稿できる(): void
    {
        $this->store(['title' => str_repeat('あ', 255)])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_トピックが空だとエラーになり増えない(): void
    {
        $this->followRedirects($this->store(['category_id' => '']))
            ->assertSee('トピックを選んでください。');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_存在しないトピックはエラーになり増えない(): void
    {
        $this->followRedirects($this->store(['category_id' => 9999]))
            ->assertSee('トピックを選んでください。');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_本文が空だとエラーになり増えない(): void
    {
        $this->followRedirects($this->store(['content' => '']))
            ->assertSee('本文を入力してください。');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_空白だけの本文でもエラーになる(): void
    {
        $this->store(['content' => "  \n  "])->assertSessionHasErrors('content');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_本文が140字ちょうどなら投稿できる(): void
    {
        $content = str_repeat('a', 140);

        $this->store(['content' => $content])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', ['content' => $content]);
    }

    public function test_全角140字の本文は投稿できる(): void
    {
        $content = str_repeat('あ', 140);

        $this->store(['content' => $content])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', ['content' => $content]);
    }

    public function test_改行を含む140字の本文は投稿できる(): void
    {
        // ブラウザは改行を \r\n で送る。画面上は 140 字（改行は 1 字）
        $this->store(['content' => str_repeat('a', 69)."\r\n".str_repeat('a', 70)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('posts', ['content' => str_repeat('a', 69)."\n".str_repeat('a', 70)]);
    }

    public function test_本文が141字だとエラーになり増えない(): void
    {
        $this->followRedirects($this->store(['content' => str_repeat('あ', 141)]))
            ->assertSee('本文は140字以内で入力してください。');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_エラーのとき入力した値が残る(): void
    {
        $response = $this->followRedirects($this->store([
            'title' => '残ってほしいタイトル',
            'content' => str_repeat('あ', 141),
        ]));

        $response->assertSee('残ってほしいタイトル')
            ->assertSee(str_repeat('あ', 141))
            ->assertSee('残り <span id="content-remaining">-1</span> 字', false);
    }

    public function test_エラーのときトピックの選択も残る(): void
    {
        $tech = Category::create(['name' => '技術メモ']);

        $this->followRedirects($this->store(['category_id' => $tech->id, 'title' => '']))
            ->assertSee('<option value="'.$tech->id.'" selected>技術メモ</option>', false);
    }

    // ---- 編集画面でも日本語のメッセージになる ----

    private function makePost(): Post
    {
        return Post::create([
            'user_id' => $this->me->id,
            'category_id' => $this->category->id,
            'title' => '元のタイトル',
            'content' => '元の本文',
        ]);
    }

    private function update(Post $post, array $data)
    {
        return $this->actingAs($this->me)
            ->from(route('posts.edit', $post))
            ->put(route('posts.update', $post), array_merge([
                'title' => '元のタイトル',
                'category_id' => $this->category->id,
                'content' => '元の本文',
            ], $data));
    }

    public function test_編集画面でもタイトルが空のメッセージは日本語になる(): void
    {
        $post = $this->makePost();

        $this->followRedirects($this->update($post, ['title' => '']))
            ->assertSee('タイトルを入力してください。');

        $this->assertSame('元のタイトル', $post->fresh()->title);
    }

    public function test_編集画面でもトピックと本文が空のメッセージは日本語になる(): void
    {
        $post = $this->makePost();

        $this->followRedirects($this->update($post, ['category_id' => '']))
            ->assertSee('トピックを選んでください。');
        $this->followRedirects($this->update($post, ['content' => '']))
            ->assertSee('本文を入力してください。');
    }

    public function test_編集画面は今の値が入っていてトピックの選択してくださいは出ない(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->me)
            ->get(route('posts.edit', $post))
            ->assertSee('value="元のタイトル"', false)
            ->assertSee('元の本文')
            ->assertSee('<option value="'.$this->category->id.'" selected>雑記</option>', false)
            ->assertDontSee('選択してください')
            ->assertSee('<span id="content-remaining">136</span>', false);
    }

    public function test_編集して更新できる(): void
    {
        $post = $this->makePost();

        $this->update($post, ['title' => '新しいタイトル', 'content' => '新しい本文'])
            ->assertRedirect(route('posts.index'));

        $this->assertSame('新しいタイトル', $post->fresh()->title);
        $this->assertSame('新しい本文', $post->fresh()->content);
    }

    // ---- 入口 ----

    public function test_ヘッダーに新規投稿ボタンが表示される(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee('<a href="'.route('posts.create').'" class="nav-link nav-primary">新規投稿</a>', false);
    }

    public function test_トップページ_ログイン中のウェルカム画面に新規投稿へのリンクが表示される(): void
    {
        $this->actingAs($this->me)
            ->get('/')
            ->assertOk()
            ->assertSee('<a href="'.route('posts.create').'" class="btn-secondary">新規投稿</a>', false)
            ->assertSee('タイムラインへ');
    }

    public function test_未ログインのトップページには新規投稿へのリンクは出ない(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee(route('posts.create'), false)
            ->assertSee('ログイン');
    }

    public function test_ヘッダーの新規投稿から作成画面へ行ける(): void
    {
        $this->actingAs($this->me)->get(route('posts.create'))->assertOk();
    }
}
