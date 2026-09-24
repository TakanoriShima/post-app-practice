<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create([
            'name' => 'わたし',
            'email' => 'me@example.com',
            'bio' => 'わたしの自己紹介です。',
        ]);
        $this->other = User::factory()->create([
            'name' => 'ほかの人',
            'email' => 'other@example.com',
            'bio' => 'ほかの人の自己紹介です。',
        ]);
        $this->category = Category::create(['name' => '雑記']);
    }

    private function makePost(User $user, string $title, string $content = '本文'): Post
    {
        return Post::create([
            'user_id' => $user->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'content' => $content,
        ]);
    }

    private function makeReply(User $user, Post $post, string $content): Reply
    {
        return Reply::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);
    }

    private function update(array $data, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->me)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), array_merge([
                'name' => 'わたし',
                'email' => 'me@example.com',
                'bio' => '',
            ], $data));
    }

    // ---- プロフィールの閲覧 ----

    public function test_ログインしていれば他人のプロフィールを見られる(): void
    {
        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertOk()
            ->assertSee('ほかの人')
            ->assertSee('ほかの人の自己紹介です。')
            ->assertSee($this->other->created_at->format('Y年n月j日').'に登録');
    }

    public function test_他人のプロフィールにはメールが出ず編集ボタンもない(): void
    {
        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertDontSee('other@example.com')
            ->assertDontSee('プロフィールを編集');
    }

    public function test_自分のプロフィールにはメールと編集ボタンが出る(): void
    {
        $this->actingAs($this->me)
            ->get(route('users.show', $this->me))
            ->assertOk()
            ->assertSee('me@example.com')
            ->assertSee('プロフィールを編集')
            ->assertSee(route('profile.edit'), false);
    }

    public function test_自己紹介が空でもプロフィールを見られる(): void
    {
        $this->other->update(['bio' => null]);

        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertOk()
            ->assertSee('ほかの人');
    }

    public function test_未ログインだとプロフィールはログイン画面へリダイレクトされる(): void
    {
        $this->get(route('users.show', $this->other))->assertRedirect('/login');
    }

    public function test_存在しないユーザーは404になる(): void
    {
        $this->actingAs($this->me)->get('/users/9999')->assertNotFound();
    }

    // ---- ポスト一覧・リプライ一覧 ----

    public function test_ポスト一覧にはそのユーザーのポストだけが出る(): void
    {
        $this->makePost($this->other, 'ほかの人のポスト');
        $this->makePost($this->me, '自分のポスト');

        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertSee('ほかの人のポスト')
            ->assertDontSee('自分のポスト');
    }

    public function test_ポスト一覧は新しい順でリプライ件数が付く(): void
    {
        $old = $this->makePost($this->other, '古いポスト');
        $old->forceFill(['created_at' => '2024-01-01 09:00:00'])->save();
        $new = $this->makePost($this->other, '新しいポスト');
        $new->forceFill(['created_at' => '2024-01-01 10:00:00'])->save();
        $this->makeReply($this->me, $new, 'リプライ1');
        $this->makeReply($this->me, $new, 'リプライ2');

        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertSeeInOrder(['新しいポスト</a> <span class="reply-count">(2)</span>', '古いポスト</a> <span class="reply-count">(0)</span>'], false);
    }

    public function test_ポストがないときはその旨が表示される(): void
    {
        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertSee('まだポストがありません。');
    }

    public function test_リプライ一覧にはそのユーザーのリプライだけが出て返信先が分かる(): void
    {
        $post = $this->makePost($this->me, '返信先のポスト');
        $this->makeReply($this->other, $post, 'ほかの人のリプライ');
        $this->makeReply($this->me, $post, '自分のリプライ');

        $this->actingAs($this->me)
            ->get(route('users.show', ['user' => $this->other, 'tab' => 'replies']))
            ->assertSee('ほかの人のリプライ')
            ->assertDontSee('自分のリプライ')
            ->assertSee('返信先のポスト')
            ->assertSee(route('posts.show', $post), false);
    }

    public function test_リプライ一覧は新しい順に並ぶ(): void
    {
        $post = $this->makePost($this->me, '返信先');
        $old = $this->makeReply($this->other, $post, '古いリプライ');
        $old->forceFill(['created_at' => '2024-01-01 09:00:00'])->save();
        $new = $this->makeReply($this->other, $post, '新しいリプライ');
        $new->forceFill(['created_at' => '2024-01-01 10:00:00'])->save();

        $this->actingAs($this->me)
            ->get(route('users.show', ['user' => $this->other, 'tab' => 'replies']))
            ->assertSeeInOrder(['新しいリプライ', '古いリプライ']);
    }

    public function test_リプライがないときはその旨が表示される(): void
    {
        $this->actingAs($this->me)
            ->get(route('users.show', ['user' => $this->other, 'tab' => 'replies']))
            ->assertSee('まだリプライがありません。');
    }

    public function test_タブを切り替えると片方の一覧だけが出る(): void
    {
        $post = $this->makePost($this->other, 'ほかの人のポスト');
        $this->makeReply($this->other, $post, 'ほかの人のリプライ');

        // ポスト一覧にだけ post-title、リプライ一覧にだけ reply-to の要素が出る
        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertSee('class="post-title"', false)
            ->assertSee('ほかの人のポスト')
            ->assertDontSee('class="reply-to"', false)
            ->assertDontSee('ほかの人のリプライ');

        $this->actingAs($this->me)
            ->get(route('users.show', ['user' => $this->other, 'tab' => 'replies']))
            ->assertSee('class="reply-to"', false)
            ->assertSee('ほかの人のリプライ')
            ->assertDontSee('class="post-title"', false);
    }

    public function test_不正なタブ指定はポスト一覧になる(): void
    {
        $this->makePost($this->other, 'ほかの人のポスト');

        $this->actingAs($this->me)
            ->get(route('users.show', ['user' => $this->other, 'tab' => 'nonsense']))
            ->assertOk()
            ->assertSee('ほかの人のポスト');
    }

    public function test_タブに件数が表示される(): void
    {
        $post = $this->makePost($this->other, 'ポスト1');
        $this->makePost($this->other, 'ポスト2');
        $this->makeReply($this->other, $post, 'リプライ');

        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertSee('ポスト（2）')
            ->assertSee('リプライ（1）');
    }

    // ---- 入口（リンク） ----

    public function test_ヘッダーの名前は自分のプロフィールへのリンクになっている(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee(route('users.show', $this->me), false);
    }

    public function test_タイムラインの投稿者は本人のプロフィールへのリンクになっている(): void
    {
        $this->makePost($this->other, 'ほかの人のポスト');

        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee(route('users.show', $this->other), false);
    }

    public function test_ポスト詳細の投稿者とリプライした人はプロフィールへのリンクになっている(): void
    {
        $post = $this->makePost($this->other, 'ほかの人のポスト');
        $this->makeReply($this->me, $post, '自分のリプライ');

        $this->actingAs($this->me)
            ->get(route('posts.show', $post))
            ->assertSee(route('users.show', $this->other), false)
            ->assertSee(route('users.show', $this->me), false);
    }

    public function test_アイコンは頭文字で表示される(): void
    {
        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertSee('class="avatar"', false)
            ->assertSee('>ほ</div>', false);
    }

    // ---- 編集 ----

    public function test_編集画面には現在の値が入っている(): void
    {
        $this->actingAs($this->me)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('value="わたし"', false)
            ->assertSee('value="me@example.com"', false)
            ->assertSee('わたしの自己紹介です。')
            ->assertSee('<span id="bio-remaining">', false);
    }

    public function test_名前とメールと自己紹介を更新できる(): void
    {
        $this->update([
            'name' => '新しい名前',
            'email' => 'new@example.com',
            'bio' => '新しい自己紹介',
        ])->assertRedirect(route('users.show', $this->me));

        $me = $this->me->fresh();
        $this->assertSame('新しい名前', $me->name);
        $this->assertSame('new@example.com', $me->email);
        $this->assertSame('新しい自己紹介', $me->bio);
    }

    public function test_更新後はプロフィールに反映される(): void
    {
        $this->update(['name' => '新しい名前']);

        $this->actingAs($this->me->fresh())
            ->get(route('users.show', $this->me))
            ->assertSee('新しい名前');
    }

    public function test_メールを変えずに保存してもエラーにならない(): void
    {
        $this->update(['name' => '名前だけ変更'])->assertSessionHasNoErrors();

        $this->assertSame('名前だけ変更', $this->me->fresh()->name);
    }

    public function test_自己紹介を空にできる(): void
    {
        $this->update(['bio' => ''])->assertSessionHasNoErrors();

        $this->assertNull($this->me->fresh()->bio);
    }

    public function test_名前が空だとエラーになり保存されない(): void
    {
        $this->update(['name' => ''])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('name');

        $this->assertSame('わたし', $this->me->fresh()->name);
    }

    public function test_名前が空のエラーメッセージは日本語で表示される(): void
    {
        $this->followRedirects($this->update(['name' => '']))
            ->assertSee('名前を入力してください。');
    }

    public function test_メールが空だとエラーになる(): void
    {
        $this->update(['email' => ''])->assertSessionHasErrors('email');

        $this->assertSame('me@example.com', $this->me->fresh()->email);
    }

    public function test_メールの形式が正しくないとエラーになる(): void
    {
        $this->update(['email' => 'これはメールではない'])->assertSessionHasErrors('email');

        $this->assertSame('me@example.com', $this->me->fresh()->email);
    }

    public function test_ほかの人のメールと重複するとエラーになる(): void
    {
        $this->followRedirects($this->update(['email' => 'other@example.com']))
            ->assertSee('このメールアドレスはすでに使われています。');

        $this->assertSame('me@example.com', $this->me->fresh()->email);
    }

    public function test_自己紹介が140字ちょうどなら保存できる(): void
    {
        $bio = str_repeat('あ', 140);

        $this->update(['bio' => $bio])->assertSessionHasNoErrors();

        $this->assertSame($bio, $this->me->fresh()->bio);
    }

    public function test_改行を含む140字の自己紹介は保存できる(): void
    {
        // ブラウザは改行を \r\n で送る。画面上は 140 字（改行は 1 字）
        $this->update(['bio' => str_repeat('a', 69)."\r\n".str_repeat('a', 70)])
            ->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('a', 69)."\n".str_repeat('a', 70), $this->me->fresh()->bio);
    }

    public function test_自己紹介が141字だとエラーになり保存されない(): void
    {
        $this->followRedirects($this->update(['bio' => str_repeat('あ', 141)]))
            ->assertSee('自己紹介は140字以内で入力してください。');

        $this->assertSame('わたしの自己紹介です。', $this->me->fresh()->bio);
    }

    public function test_エラーのとき入力した値が残る(): void
    {
        $this->followRedirects($this->update(['name' => '', 'bio' => '残ってほしい自己紹介']))
            ->assertSee('残ってほしい自己紹介');
    }

    public function test_ほかのユーザーの情報は変わらない(): void
    {
        $this->update([
            'name' => '新しい名前',
            'id' => $this->other->id,
            'user_id' => $this->other->id,
        ]);

        $other = $this->other->fresh();
        $this->assertSame('ほかの人', $other->name);
        $this->assertSame('other@example.com', $other->email);
        $this->assertSame('新しい名前', $this->me->fresh()->name);
    }

    public function test_未ログインでは編集画面も更新もできない(): void
    {
        $this->get(route('profile.edit'))->assertRedirect('/login');
        $this->put(route('profile.update'), ['name' => 'x', 'email' => 'x@example.com'])
            ->assertRedirect('/login');

        $this->assertSame('わたし', $this->me->fresh()->name);
    }
}
