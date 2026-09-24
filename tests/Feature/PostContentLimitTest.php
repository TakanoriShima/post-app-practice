<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostContentLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $category = Category::create(['name' => '雑記']);
        $this->post = Post::create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'title' => 'タイトル',
            'content' => '元の本文',
        ]);
    }

    private function update(string $content)
    {
        return $this->actingAs($this->user)
            ->from(route('posts.edit', $this->post))
            ->put(route('posts.update', $this->post), [
                'title' => 'タイトル',
                'category_id' => $this->post->category_id,
                'content' => $content,
            ]);
    }

    public function test_140字ちょうどの本文は更新できる(): void
    {
        $content = str_repeat('a', 140);

        $this->update($content)->assertRedirect(route('posts.index'));

        $this->assertSame($content, $this->post->fresh()->content);
    }

    public function test_全角140字の本文は更新できる(): void
    {
        $content = str_repeat('あ', 140);

        $this->update($content)->assertRedirect(route('posts.index'));

        $this->assertSame($content, $this->post->fresh()->content);
    }

    public function test_改行を含む140字の本文は更新できる(): void
    {
        // ブラウザは改行を \r\n で送る。画面上は 140 字（改行は 1 字）
        $this->update(str_repeat('a', 69)."\r\n".str_repeat('a', 70))
            ->assertRedirect(route('posts.index'));

        $this->assertSame(str_repeat('a', 69)."\n".str_repeat('a', 70), $this->post->fresh()->content);
    }

    public function test_141字の本文はエラーになり保存されない(): void
    {
        $this->update(str_repeat('あ', 141))
            ->assertRedirect(route('posts.edit', $this->post))
            ->assertSessionHasErrors('content');

        $this->assertSame('元の本文', $this->post->fresh()->content);
    }

    public function test_エラーメッセージは日本語で表示される(): void
    {
        $this->followRedirects($this->update(str_repeat('あ', 141)))
            ->assertSee('本文は140字以内で入力してください。');
    }

    public function test_編集画面に残り文字数が表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('posts.edit', $this->post))
            ->assertSee('残り')
            ->assertSee('<span id="content-remaining">136</span>', false);
    }
}
