<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    private User $third;

    private Category $category;

    private Post $post;

    private Reply $reply;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['name' => 'わたし']);
        $this->other = User::factory()->create(['name' => 'ほかの人']);
        $this->third = User::factory()->create(['name' => 'さんばんめ']);
        $this->category = Category::create(['name' => '雑記']);
        $this->post = $this->makePost($this->other, 'ほかの人のポスト');
        $this->reply = $this->makeReply($this->other, $this->post, 'ほかの人のリプライ');
    }

    private function makePost(User $user, string $title): Post
    {
        return Post::create([
            'user_id' => $user->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'content' => '本文',
        ]);
    }

    private function makeReply(User $user, Post $post, string $content): Reply
    {
        return Reply::create(['post_id' => $post->id, 'user_id' => $user->id, 'content' => $content]);
    }

    private function likePost(Post $post, User ...$users): void
    {
        foreach ($users as $user) {
            $post->likedBy()->attach($user->id);
        }
    }

    private function likeReply(Reply $reply, User ...$users): void
    {
        foreach ($users as $user) {
            $reply->likedBy()->attach($user->id);
        }
    }

    // ---- ポストにいいねする・取り消す ----

    public function test_ポストにいいねすると1件増える(): void
    {
        $this->actingAs($this->me)->post(route('posts.like.store', $this->post))->assertRedirect();

        $this->assertDatabaseHas('post_likes', ['post_id' => $this->post->id, 'user_id' => $this->me->id]);
        $this->assertDatabaseCount('post_likes', 1);
    }

    public function test_同じポストに2回いいねしても1件のままでエラーにならない(): void
    {
        $this->actingAs($this->me)->post(route('posts.like.store', $this->post));
        $this->actingAs($this->me)->post(route('posts.like.store', $this->post))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('post_likes', 1);
    }

    public function test_同じ人が同じポストに2回いいねできないのはテーブルでも守られる(): void
    {
        $this->likePost($this->post, $this->me);

        $this->expectException(QueryException::class);

        DB::table('post_likes')->insert(['post_id' => $this->post->id, 'user_id' => $this->me->id]);
    }

    public function test_ポストのいいねを取り消すと1件減る(): void
    {
        $this->likePost($this->post, $this->me);

        $this->actingAs($this->me)->delete(route('posts.like.destroy', $this->post))->assertRedirect();

        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_いいねしていないポストを取り消してもエラーにならない(): void
    {
        $this->actingAs($this->me)->delete(route('posts.like.destroy', $this->post))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_ほかの人のいいねは取り消せない(): void
    {
        $this->likePost($this->post, $this->other, $this->third);

        $this->actingAs($this->me)->delete(route('posts.like.destroy', $this->post));

        $this->assertDatabaseCount('post_likes', 2);
        $this->assertDatabaseHas('post_likes', ['post_id' => $this->post->id, 'user_id' => $this->other->id]);
    }

    public function test_自分のポストにもいいねできる(): void
    {
        $mine = $this->makePost($this->me, '自分のポスト');

        $this->actingAs($this->me)->post(route('posts.like.store', $mine));

        $this->assertDatabaseHas('post_likes', ['post_id' => $mine->id, 'user_id' => $this->me->id]);
    }

    public function test_未ログインではポストにいいねも取り消しもできない(): void
    {
        $this->post(route('posts.like.store', $this->post))->assertRedirect('/login');
        $this->delete(route('posts.like.destroy', $this->post))->assertRedirect('/login');

        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_存在しないポストへのいいねは404になる(): void
    {
        $this->actingAs($this->me)->post('/posts/9999/like')->assertNotFound();
        $this->actingAs($this->me)->delete('/posts/9999/like')->assertNotFound();
    }

    // ---- リプライにいいねする・取り消す ----

    public function test_リプライにいいねすると1件増える(): void
    {
        $this->actingAs($this->me)->post(route('replies.like.store', $this->reply))->assertRedirect();

        $this->assertDatabaseHas('reply_likes', ['reply_id' => $this->reply->id, 'user_id' => $this->me->id]);
        $this->assertDatabaseCount('reply_likes', 1);
    }

    public function test_同じリプライに2回いいねしても1件のままでエラーにならない(): void
    {
        $this->actingAs($this->me)->post(route('replies.like.store', $this->reply));
        $this->actingAs($this->me)->post(route('replies.like.store', $this->reply))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reply_likes', 1);
    }

    public function test_同じ人が同じリプライに2回いいねできないのはテーブルでも守られる(): void
    {
        $this->likeReply($this->reply, $this->me);

        $this->expectException(QueryException::class);

        DB::table('reply_likes')->insert(['reply_id' => $this->reply->id, 'user_id' => $this->me->id]);
    }

    public function test_リプライのいいねを取り消すと1件減る(): void
    {
        $this->likeReply($this->reply, $this->me);

        $this->actingAs($this->me)->delete(route('replies.like.destroy', $this->reply))->assertRedirect();

        $this->assertDatabaseCount('reply_likes', 0);
    }

    public function test_いいねしていないリプライを取り消してもエラーにならない(): void
    {
        $this->actingAs($this->me)->delete(route('replies.like.destroy', $this->reply))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_ほかの人のリプライのいいねは取り消せない(): void
    {
        $this->likeReply($this->reply, $this->other, $this->third);

        $this->actingAs($this->me)->delete(route('replies.like.destroy', $this->reply));

        $this->assertDatabaseCount('reply_likes', 2);
    }

    public function test_自分のリプライにもいいねできる(): void
    {
        $mine = $this->makeReply($this->me, $this->post, '自分のリプライ');

        $this->actingAs($this->me)->post(route('replies.like.store', $mine));

        $this->assertDatabaseHas('reply_likes', ['reply_id' => $mine->id, 'user_id' => $this->me->id]);
    }

    public function test_未ログインではリプライにいいねも取り消しもできない(): void
    {
        $this->post(route('replies.like.store', $this->reply))->assertRedirect('/login');
        $this->delete(route('replies.like.destroy', $this->reply))->assertRedirect('/login');

        $this->assertDatabaseCount('reply_likes', 0);
    }

    public function test_存在しないリプライへのいいねは404になる(): void
    {
        $this->actingAs($this->me)->post('/replies/9999/like')->assertNotFound();
        $this->actingAs($this->me)->delete('/replies/9999/like')->assertNotFound();
    }

    public function test_ポストのいいねとリプライのいいねは別々に数えられる(): void
    {
        $this->likePost($this->post, $this->me);

        $this->assertDatabaseCount('post_likes', 1);
        $this->assertDatabaseCount('reply_likes', 0);
    }

    // ---- 押した画面に戻る ----

    public function test_押した画面に押したポストの位置つきで戻る(): void
    {
        $this->actingAs($this->me)
            ->from(route('posts.index'))
            ->post(route('posts.like.store', $this->post))
            ->assertRedirect(route('posts.index').'#post-'.$this->post->id);
    }

    public function test_取り消したときも押した画面と位置に戻る(): void
    {
        $this->likePost($this->post, $this->me);

        $this->actingAs($this->me)
            ->from(route('posts.show', $this->post))
            ->delete(route('posts.like.destroy', $this->post))
            ->assertRedirect(route('posts.show', $this->post).'#post-'.$this->post->id);
    }

    public function test_リプライを押したときはリプライの位置に戻る(): void
    {
        $this->actingAs($this->me)
            ->from(route('posts.show', $this->post))
            ->post(route('replies.like.store', $this->reply))
            ->assertRedirect(route('posts.show', $this->post).'#reply-'.$this->reply->id);
    }

    public function test_プロフィールのリプライタブから押すとタブを保ったまま戻る(): void
    {
        $from = route('users.show', ['user' => $this->other, 'tab' => 'replies']);

        $this->actingAs($this->me)
            ->from($from)
            ->post(route('replies.like.store', $this->reply))
            ->assertRedirect($from.'#reply-'.$this->reply->id);
    }

    public function test_戻り先が分からないときの行き先(): void
    {
        $this->actingAs($this->me)
            ->post(route('posts.like.store', $this->post))
            ->assertRedirect(route('posts.index').'#post-'.$this->post->id);

        $this->actingAs($this->me)
            ->post(route('replies.like.store', $this->reply))
            ->assertRedirect(route('posts.show', $this->post).'#reply-'.$this->reply->id);
    }

    // ---- 表示: 見た目（灰色の枠のハート／ピンクの塗りつぶしのハート） ----

    public function test_押していないときは灰色の枠のハートでいいねするフォームが出る(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee('action="'.route('posts.like.store', $this->post).'"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-label="いいねする"', false)
            ->assertSee('class="like-btn"', false)
            ->assertDontSee('like-btn liked')
            ->assertDontSee('name="_method" value="DELETE"', false);
    }

    public function test_押したときはピンクの塗りつぶしのハートで取り消すフォームが出る(): void
    {
        $this->likePost($this->post, $this->me);

        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee('action="'.route('posts.like.destroy', $this->post).'"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('class="like-btn liked"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-label="いいねを取り消す（1件）"', false);
    }

    public function test_色と形の定義がある(): void
    {
        $html = $this->actingAs($this->me)->get(route('posts.index'))->getContent();

        // 灰色の枠のハート
        $this->assertStringContainsString('color: #7A8590', $html);
        $this->assertStringContainsString('fill: none; stroke: currentColor', $html);
        // ピンクの塗りつぶしのハート（件数の文字は少し濃いピンク）
        $this->assertStringContainsString('.like-btn.liked { color: #F91880; }', $html);
        $this->assertStringContainsString('.like-btn.liked svg { fill: currentColor; }', $html);
        $this->assertStringContainsString('.like-btn.liked .like-count { color: #C0146B; }', $html);
        // カーソルを乗せたとき
        $this->assertStringContainsString('.like-btn:hover { color: #F91880; background: rgba(249, 24, 128, 0.1); }', $html);
        // キーボード操作の枠
        $this->assertStringContainsString('.like-btn:focus-visible', $html);
    }

    public function test_ハートの部品のスタイルは1ページに1回だけ出る(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makePost($this->other, "ポスト{$i}");
        }

        $html = $this->actingAs($this->me)->get(route('posts.index'))->getContent();

        $this->assertSame(1, substr_count($html, '.like-form { display: inline-flex; align-items: center; }'));
        $this->assertGreaterThanOrEqual(6, substr_count($html, 'class="like-form"'));
    }

    // ---- 表示: 件数（0件は件数を出さず、ハートだけ） ----

    public function test_件数が1以上のときだけ件数が出る(): void
    {
        $this->likePost($this->post, $this->me, $this->third);

        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee('<span class="like-count">2</span>', false);
    }

    public function test_0件のときは件数を出さずハートだけが出る(): void
    {
        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertDontSee('<span class="like-count">', false)
            ->assertSee('class="like-btn"', false);
    }

    public function test_件数は自分以外のいいねも数える(): void
    {
        $this->likePost($this->post, $this->other, $this->third);

        // 自分は押していないので灰色の枠のまま、件数は2
        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee('<span class="like-count">2</span>', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertDontSee('like-btn liked');
    }

    public function test_押したかどうかは見ている人ごとに決まる(): void
    {
        $this->likePost($this->post, $this->third);

        $this->actingAs($this->third)->get(route('posts.index'))->assertSee('like-btn liked');
        $this->actingAs($this->me)->get(route('posts.index'))->assertDontSee('like-btn liked');
    }

    public function test_ポストごとに件数が別々に出る(): void
    {
        $second = $this->makePost($this->other, '二つ目のポスト');
        $this->likePost($this->post, $this->me);
        $this->likePost($second, $this->me, $this->third, $this->other);

        $html = $this->actingAs($this->me)->get(route('posts.index'))->getContent();

        $this->assertStringContainsString('<span class="like-count">1</span>', $html);
        $this->assertStringContainsString('<span class="like-count">3</span>', $html);
    }

    // ---- 表示: 4か所 ----

    public function test_ポスト詳細のポストとリプライにハートが出る(): void
    {
        $this->likePost($this->post, $this->me);
        $this->likeReply($this->reply, $this->third);

        $this->actingAs($this->me)
            ->get(route('posts.show', $this->post))
            ->assertSee('action="'.route('posts.like.destroy', $this->post).'"', false)
            ->assertSee('action="'.route('replies.like.store', $this->reply).'"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('id="post-'.$this->post->id.'"', false)
            ->assertSee('id="reply-'.$this->reply->id.'"', false)
            ->assertSee('<span class="like-count">1</span>', false);
    }

    public function test_ポスト詳細の自分のリプライには削除とハートの両方が出る(): void
    {
        $mine = $this->makeReply($this->me, $this->post, '自分のリプライ');

        $this->actingAs($this->me)
            ->get(route('posts.show', $this->post))
            ->assertSee('action="'.route('replies.like.store', $mine).'"', false)
            ->assertSee('action="'.route('replies.destroy', $mine).'"', false);
    }

    public function test_プロフィールのポスト一覧にハートが出る(): void
    {
        $this->likePost($this->post, $this->me, $this->third);

        $this->actingAs($this->me)
            ->get(route('users.show', $this->other))
            ->assertSee('action="'.route('posts.like.destroy', $this->post).'"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('id="post-'.$this->post->id.'"', false)
            ->assertSee('<span class="like-count">2</span>', false);
    }

    public function test_プロフィールのリプライ一覧にハートが出る(): void
    {
        $this->likeReply($this->reply, $this->me);

        $this->actingAs($this->me)
            ->get(route('users.show', ['user' => $this->other, 'tab' => 'replies']))
            ->assertSee('action="'.route('replies.like.destroy', $this->reply).'"', false)
            ->assertSee('id="reply-'.$this->reply->id.'"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('<span class="like-count">1</span>', false);
    }

    // ---- 消えたときの後始末 ----

    public function test_ポストを消すとポストのいいねとリプライのいいねも消える(): void
    {
        $this->likePost($this->post, $this->me, $this->third);
        $this->likeReply($this->reply, $this->me);

        $this->actingAs($this->other)
            ->delete(route('posts.destroy', $this->post))
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('post_likes', 0);
        $this->assertDatabaseCount('reply_likes', 0);
    }

    public function test_リプライを消すとそのいいねも消えてポストのいいねは残る(): void
    {
        $this->likePost($this->post, $this->me);
        $this->likeReply($this->reply, $this->me, $this->third);

        $this->actingAs($this->other)
            ->delete(route('replies.destroy', $this->reply))
            ->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseCount('reply_likes', 0);
        $this->assertDatabaseCount('post_likes', 1);
    }

    public function test_ほかのポストのいいねは消えない(): void
    {
        $another = $this->makePost($this->other, '残るポスト');
        $this->likePost($this->post, $this->me);
        $this->likePost($another, $this->me);

        $this->actingAs($this->other)->delete(route('posts.destroy', $this->post));

        $this->assertDatabaseCount('post_likes', 1);
        $this->assertDatabaseHas('post_likes', ['post_id' => $another->id]);
    }

    // ---- クエリの数が増えない ----

    private function queryCount(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->me)->get($url)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_タイムラインはポストが増えてもクエリの数が変わらない(): void
    {
        $this->likePost($this->post, $this->me);
        $before = $this->queryCount(route('posts.index'));

        foreach (range(1, 9) as $i) {
            $this->likePost($this->makePost($this->other, "ポスト{$i}"), $this->me, $this->third);
        }

        $this->assertSame($before, $this->queryCount(route('posts.index')));
    }

    public function test_ポスト詳細はリプライが増えてもクエリの数が変わらない(): void
    {
        $this->likeReply($this->reply, $this->me);
        $before = $this->queryCount(route('posts.show', $this->post));

        foreach (range(1, 9) as $i) {
            $this->likeReply($this->makeReply($this->other, $this->post, "リプライ{$i}"), $this->me, $this->third);
        }

        $this->assertSame($before, $this->queryCount(route('posts.show', $this->post)));
    }

    public function test_プロフィールは一覧が増えてもクエリの数が変わらない(): void
    {
        $this->likePost($this->post, $this->me);
        $this->likeReply($this->reply, $this->me);
        $postsBefore = $this->queryCount(route('users.show', $this->other));
        $repliesBefore = $this->queryCount(route('users.show', ['user' => $this->other, 'tab' => 'replies']));

        foreach (range(1, 9) as $i) {
            $post = $this->makePost($this->other, "ポスト{$i}");
            $this->likePost($post, $this->me, $this->third);
            $this->likeReply($this->makeReply($this->other, $post, "リプライ{$i}"), $this->me);
        }

        $this->assertSame($postsBefore, $this->queryCount(route('users.show', $this->other)));
        $this->assertSame($repliesBefore, $this->queryCount(route('users.show', ['user' => $this->other, 'tab' => 'replies'])));
    }

    // ---- 既存の動きが変わらない ----

    public function test_いいねがあってもタイムラインの並びとリプライ件数は変わらない(): void
    {
        $old = $this->makePost($this->other, '古いポスト');
        $old->forceFill(['created_at' => '2024-01-01 09:00:00'])->save();
        $this->likePost($old, $this->me, $this->third);
        $this->makeReply($this->me, $this->post, 'もう一つのリプライ');

        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSeeInOrder(['ほかの人のポスト</a> <span class="reply-count">(2)</span>', '古いポスト</a> <span class="reply-count">(0)</span>'], false);
    }

    public function test_ヘッダーのいいね機能は自分のいいねを消さずに編集や削除も動く(): void
    {
        $mine = $this->makePost($this->me, '自分のポスト');
        $this->likePost($mine, $this->third);

        $this->actingAs($this->me)->get(route('posts.edit', $mine))->assertOk();
        $this->actingAs($this->me)
            ->get(route('posts.index'))
            ->assertSee('action="'.route('posts.destroy', $mine).'"', false)
            ->assertSee(route('posts.edit', $mine), false);
    }

    // ---- 画面遷移しない（裏からの送信。JavaScript が今のボタンを置き換える） ----

    // fetch と同じヘッダー（X-Requested-With と、既定の Accept のワイルドカード）
    private function ajaxServer(): array
    {
        return ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => '*/*'];
    }

    private function ajax(string $method, string $url, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->me)->call($method, $url, [], [], [], $this->ajaxServer());
    }

    public function test_裏からのいいねはリダイレクトせずボタンの断片を返す(): void
    {
        $response = $this->ajax('POST', route('posts.like.store', $this->post));

        $response->assertOk();
        $this->assertFalse($response->isRedirect());
        $this->assertDatabaseHas('post_likes', ['post_id' => $this->post->id, 'user_id' => $this->me->id]);
    }

    public function test_断片は押した状態のボタンで件数が最新になる(): void
    {
        $this->likePost($this->post, $this->third);

        $this->ajax('POST', route('posts.like.store', $this->post))
            ->assertSee('class="like-btn liked"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('aria-label="いいねを取り消す（2件）"', false)
            ->assertSee('<span class="like-count">2</span>', false);
    }

    public function test_裏からの取り消しは押していない状態のボタンの断片を返す(): void
    {
        $this->likePost($this->post, $this->me, $this->third);

        // fetch は POST に _method=DELETE を付けて送る（フォームと同じ）
        $response = $this->actingAs($this->me)->call(
            'POST', route('posts.like.destroy', $this->post), ['_method' => 'DELETE'], [], [], $this->ajaxServer()
        );

        $response->assertOk()
            ->assertSee('class="like-btn"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertDontSee('name="_method" value="DELETE"', false)
            ->assertSee('<span class="like-count">1</span>', false);
        $this->assertFalse($response->isRedirect());
        $this->assertDatabaseCount('post_likes', 1);
    }

    public function test_裏から取り消して0件になると件数を出さない(): void
    {
        $this->likePost($this->post, $this->me);

        $this->ajax('DELETE', route('posts.like.destroy', $this->post))
            ->assertOk()
            ->assertSee('aria-pressed="false"', false)
            ->assertDontSee('like-count');
    }

    public function test_リプライも裏からの送信で断片を返す(): void
    {
        $this->ajax('POST', route('replies.like.store', $this->reply))
            ->assertOk()
            ->assertSee('action="'.route('replies.like.destroy', $this->reply).'"', false)
            ->assertSee('class="like-btn liked"', false)
            ->assertSee('<span class="like-count">1</span>', false);

        $this->ajax('DELETE', route('replies.like.destroy', $this->reply))
            ->assertOk()
            ->assertSee('class="like-btn"', false)
            ->assertDontSee('like-count');

        $this->assertDatabaseCount('reply_likes', 0);
    }

    public function test_断片は1つのフォームだけでスタイルとスクリプトを含まない(): void
    {
        $html = $this->ajax('POST', route('posts.like.store', $this->post))->getContent();

        $this->assertStringStartsWith('<form class="like-form"', trim($html));
        $this->assertStringEndsWith('</form>', trim($html));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_断片には新しいトークンとログイン画面の行き先が入る(): void
    {
        $this->ajax('POST', route('posts.like.store', $this->post))
            ->assertSee('name="_token"', false)
            ->assertSee('data-login-url="'.route('login').'"', false);
    }

    public function test_裏からの送信でも付いているものに付けても1件のまま(): void
    {
        $this->ajax('POST', route('posts.like.store', $this->post))->assertOk();
        $this->ajax('POST', route('posts.like.store', $this->post))->assertOk();

        $this->assertDatabaseCount('post_likes', 1);
    }

    public function test_裏から取り消せるのは自分のいいねだけ(): void
    {
        $this->likePost($this->post, $this->third);

        $this->ajax('DELETE', route('posts.like.destroy', $this->post))
            ->assertOk()
            ->assertSee('<span class="like-count">1</span>', false)
            ->assertSee('aria-pressed="false"', false);

        $this->assertDatabaseHas('post_likes', ['post_id' => $this->post->id, 'user_id' => $this->third->id]);
    }

    public function test_未ログインで裏から送ると401になりログイン画面へのリダイレクトではない(): void
    {
        $response = $this->call('POST', route('posts.like.store', $this->post), [], [], [], $this->ajaxServer());

        $response->assertUnauthorized();
        $this->assertFalse($response->isRedirect());
        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_存在しないものを裏から送ると404になる(): void
    {
        $this->ajax('POST', '/posts/9999/like')->assertNotFound();
        $this->ajax('POST', '/replies/9999/like')->assertNotFound();
    }

    public function test_普通のフォーム送信は今までどおりリダイレクトする(): void
    {
        $response = $this->actingAs($this->me)
            ->from(route('posts.index'))
            ->post(route('posts.like.store', $this->post));

        $response->assertRedirect(route('posts.index').'#post-'.$this->post->id);
        $this->assertDatabaseCount('post_likes', 1);
    }

    public function test_ページにはスクリプトとアニメーションの定義が1回だけ出る(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makePost($this->other, "ポスト{$i}");
        }

        $html = $this->actingAs($this->me)->get(route('posts.index'))->getContent();

        $this->assertSame(1, substr_count($html, "document.addEventListener('submit'"));
        $this->assertSame(1, substr_count($html, '@keyframes like-pop'));
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce) { .like-pop { animation: none; } }', $html);
        $this->assertStringContainsString('.like-error', $html);
        $this->assertStringContainsString("form.getAttribute('aria-busy') === 'true'", $html);
        $this->assertStringContainsString('window.location.href = form.dataset.loginUrl', $html);
        $this->assertStringContainsString('window.location.reload()', $html);
    }

    public function test_ポスト詳細とプロフィールにもスクリプトが1回だけ出る(): void
    {
        $this->likePost($this->post, $this->me);
        $this->likeReply($this->reply, $this->me);

        foreach ([
            route('posts.show', $this->post),
            route('users.show', $this->other),
            route('users.show', ['user' => $this->other, 'tab' => 'replies']),
        ] as $url) {
            $html = $this->actingAs($this->me)->get($url)->getContent();

            $this->assertSame(1, substr_count($html, "document.addEventListener('submit'"), $url);
        }
    }

    public function test_裏からの送信のクエリの数はいいねの数に関わらず一定(): void
    {
        $empty = $this->makePost($this->other, 'いいねのないポスト');
        $liked = $this->makePost($this->other, 'いいねの多いポスト');
        foreach (range(1, 5) as $i) {
            $this->likePost($liked, User::factory()->create());
        }

        $queries = function (Post $post): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->ajax('POST', route('posts.like.store', $post))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->assertSame($queries($empty), $queries($liked));
    }
}
