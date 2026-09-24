<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password',
        ]);
    }

    private function login()
    {
        return $this->post('/login', ['email' => 'login@example.com', 'password' => 'password']);
    }

    public function test_ログインした直後はポスト一覧にリダイレクトされる(): void
    {
        $this->login()->assertRedirect('http://localhost/posts');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_保護されたページからログイン画面に来てもログイン後はポスト一覧に行く(): void
    {
        // 未ログインでポストの編集画面を開こうとして、ログイン画面に飛ばされる（/posts 以外のページ）
        $this->get('/posts/1/edit')->assertRedirect('/login');

        $this->login()->assertRedirect('http://localhost/posts');
    }

    public function test_ログイン画面を開いたあとでもポスト一覧に行く(): void
    {
        $this->get('/login')->assertOk();

        $this->login()->assertRedirect('http://localhost/posts');
    }

    public function test_パスワードが違うとログインできずポスト一覧にも行かない(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'login@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_ログイン済みでログイン画面を開くとポスト一覧に行く(): void
    {
        $this->actingAs($this->user)->get('/login')->assertRedirect('/posts');
    }
}
