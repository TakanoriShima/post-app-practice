<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->me = User::factory()->create(['name' => 'わたし', 'email' => 'me@example.com']);
        $this->other = User::factory()->create(['name' => 'ほかの人', 'email' => 'other@example.com']);
    }

    private function update(array $data = [], ?User $user = null)
    {
        $user ??= $this->me;

        return $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), array_merge([
                'name' => $user->name,
                'email' => $user->email,
                'bio' => '',
            ], $data));
    }

    /** 保存済みの画像ファイルを、幅・高さ・MIME 付きで読む */
    private function stored(string $path): array
    {
        $binary = Storage::disk('public')->get($path);

        return [
            'info' => getimagesizefromstring($binary),
            'image' => imagecreatefromstring($binary),
        ];
    }

    /** 左半分が赤、右半分が青の JPEG に、EXIF の向き（Orientation）を埋め込んだファイル */
    private function jpegWithOrientation(int $orientation): UploadedFile
    {
        $image = imagecreatetruecolor(200, 100);
        imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 100, 0, 199, 99, imagecolorallocate($image, 0, 0, 255));

        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = ob_get_clean();

        // APP1（Exif）セグメント: リトルエンディアンの TIFF ヘッダー + Orientation だけの IFD
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 1).pack('vvVv', 0x0112, 3, 1, $orientation).pack('v', 0)
            .pack('V', 0);
        $app1 = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }

    // ---- アップロード ----

    public function test_画像をアップロードするとアバターとして保存される(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('me.jpg', 600, 600)])
            ->assertRedirect(route('users.show', $this->me))
            ->assertSessionHasNoErrors();

        $path = $this->me->fresh()->avatar_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_保存される画像は256px四方のwebpになる(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('me.png', 600, 300)]);

        ['info' => $info] = $this->stored($this->me->fresh()->avatar_path);

        $this->assertSame(256, $info[0]);
        $this->assertSame(256, $info[1]);
        $this->assertSame('image/webp', $info['mime']);
    }

    public function test_jpeg_png_webp_gifを受け付ける(): void
    {
        foreach (['a.jpg', 'a.jpeg', 'a.png', 'a.webp', 'a.gif'] as $name) {
            $this->update(['avatar' => UploadedFile::fake()->image($name, 100, 100)])
                ->assertSessionHasNoErrors();

            $this->assertNotNull($this->me->fresh()->avatar_path, $name);
        }
    }

    public function test_スマホ写真のexifの向きが反映される(): void
    {
        // 向き 6（時計回りに 90 度）: 元の左半分（赤）が上に、右半分（青）が下に来る
        $this->update(['avatar' => $this->jpegWithOrientation(6)])->assertSessionHasNoErrors();

        ['image' => $image] = $this->stored($this->me->fresh()->avatar_path);

        $top = imagecolorsforindex($image, imagecolorat($image, 128, 20));
        $bottom = imagecolorsforindex($image, imagecolorat($image, 128, 236));

        $this->assertGreaterThan(200, $top['red']);
        $this->assertLessThan(60, $top['blue']);
        $this->assertGreaterThan(200, $bottom['blue']);
        $this->assertLessThan(60, $bottom['red']);
    }

    public function test_向きの指定がなければ左が赤右が青のままになる(): void
    {
        $this->update(['avatar' => $this->jpegWithOrientation(1)])->assertSessionHasNoErrors();

        ['image' => $image] = $this->stored($this->me->fresh()->avatar_path);

        $left = imagecolorsforindex($image, imagecolorat($image, 20, 128));
        $right = imagecolorsforindex($image, imagecolorat($image, 236, 128));

        $this->assertGreaterThan(200, $left['red']);
        $this->assertGreaterThan(200, $right['blue']);
    }

    public function test_保存される画像にはexifが残らない(): void
    {
        $this->update(['avatar' => $this->jpegWithOrientation(6)]);

        $binary = Storage::disk('public')->get($this->me->fresh()->avatar_path);

        $this->assertStringNotContainsString('Exif', $binary);
    }

    public function test_5mbちょうどの画像は受け付ける(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('big.jpg', 100, 100)->size(5120)])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->me->fresh()->avatar_path);
    }

    public function test_縦横5000pxちょうどの画像は受け付ける(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('wide.png', 5000, 10)])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->me->fresh()->avatar_path);
    }

    // ---- 拒否される入力 ----

    public function test_画像でないファイルはエラーになり保存されない(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->createWithContent('fake.jpg', 'これは画像ではありません')])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($this->me->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_非対応の形式は日本語のメッセージでエラーになる(): void
    {
        $svg = UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->followRedirects($this->update(['avatar' => $svg]))
            ->assertSee('アバターには JPEG、PNG、WebP、GIF の画像を選んでください。');

        $this->assertNull($this->me->fresh()->avatar_path);
    }

    public function test_pdfはエラーになる(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_5mbを超える画像はエラーになり保存されない(): void
    {
        $this->followRedirects($this->update(['avatar' => UploadedFile::fake()->image('big.jpg', 100, 100)->size(5121)]))
            ->assertSee('アバター画像は5MB以内にしてください。');

        $this->assertNull($this->me->fresh()->avatar_path);
    }

    public function test_縦横が5000pxを超える画像はエラーになり保存されない(): void
    {
        $this->followRedirects($this->update(['avatar' => UploadedFile::fake()->image('huge.png', 5001, 10)]))
            ->assertSee('アバター画像の縦横は5000px以内にしてください。');

        $this->assertNull($this->me->fresh()->avatar_path);
    }

    public function test_アバターがエラーのとき名前など他の項目も保存されない(): void
    {
        $this->update([
            'name' => '変えたい名前',
            'avatar' => UploadedFile::fake()->createWithContent('fake.jpg', 'テキスト'),
        ])->assertSessionHasErrors('avatar');

        $this->assertSame('わたし', $this->me->fresh()->name);
    }

    // ---- 差し替え・削除 ----

    public function test_差し替えると古いファイルが消える(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('first.jpg')]);
        $old = $this->me->fresh()->avatar_path;

        $this->update(['avatar' => UploadedFile::fake()->image('second.jpg')]);
        $new = $this->me->fresh()->avatar_path;

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_削除すると未設定に戻りファイルも消える(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('me.jpg')]);
        $path = $this->me->fresh()->avatar_path;

        $this->update(['remove_avatar' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($this->me->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_削除と新しい画像を同時に送ると新しい画像が優先される(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('first.jpg')]);
        $old = $this->me->fresh()->avatar_path;

        $this->update(['avatar' => UploadedFile::fake()->image('second.jpg'), 'remove_avatar' => '1']);
        $new = $this->me->fresh()->avatar_path;

        $this->assertNotNull($new);
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_画像を送らずに保存してもアバターは変わらない(): void
    {
        $this->update(['avatar' => UploadedFile::fake()->image('me.jpg')]);
        $path = $this->me->fresh()->avatar_path;

        $this->update(['name' => '名前だけ変更']);

        $this->assertSame($path, $this->me->fresh()->avatar_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_未設定のときは削除のチェックが出ず設定済みなら出る(): void
    {
        $this->actingAs($this->me)->get(route('profile.edit'))
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="avatar"', false)
            ->assertDontSee('画像を削除して初期アイコンに戻す');

        $this->update(['avatar' => UploadedFile::fake()->image('me.jpg')]);

        $this->actingAs($this->me->fresh())->get(route('profile.edit'))
            ->assertSee('画像を削除して初期アイコンに戻す');
    }

    // ---- 他人・未ログイン ----

    public function test_他人のアバターは変更できない(): void
    {
        $this->other->forceFill(['avatar_path' => 'avatars/other.webp'])->save();

        $this->update([
            'avatar' => UploadedFile::fake()->image('me.jpg'),
            'avatar_path' => 'avatars/hacked.webp',
            'id' => $this->other->id,
            'user_id' => $this->other->id,
        ]);

        $this->assertSame('avatars/other.webp', $this->other->fresh()->avatar_path);
        $this->assertNotSame('avatars/hacked.webp', $this->me->fresh()->avatar_path);
    }

    public function test_avatar_pathを直接送っても書き換えられない(): void
    {
        $this->update(['avatar_path' => 'avatars/hacked.webp']);

        $this->assertNull($this->me->fresh()->avatar_path);
    }

    public function test_未ログインではアップロードできない(): void
    {
        $this->put(route('profile.update'), [
            'name' => 'x',
            'email' => 'x@example.com',
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ])->assertRedirect('/login');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // ---- 表示 ----

    private function setAvatar(User $user): string
    {
        $this->update(['avatar' => UploadedFile::fake()->image('a.jpg')], $user);

        return $user->fresh()->avatarUrl();
    }

    public function test_未設定のユーザーは頭文字のアイコンが表示される(): void
    {
        $post = Post::create([
            'user_id' => $this->other->id,
            'category_id' => Category::create(['name' => '雑記'])->id,
            'title' => 'タイトル',
            'content' => '本文',
        ]);

        $this->actingAs($this->me)->get(route('posts.index'))
            ->assertSee('>ほ</div>', false)
            ->assertDontSee('<img class="avatar"', false);

        $this->actingAs($this->me)->get(route('posts.show', $post))
            ->assertSee('>ほ</div>', false);
    }

    public function test_タイムラインのポストの投稿者に丸い画像が表示される(): void
    {
        $url = $this->setAvatar($this->other);
        Post::create([
            'user_id' => $this->other->id,
            'category_id' => Category::create(['name' => '雑記'])->id,
            'title' => 'タイトル',
            'content' => '本文',
        ]);

        $this->actingAs($this->me)->get(route('posts.index'))
            ->assertSee('<img class="avatar" src="'.$url.'" alt="ほかの人"', false)
            ->assertSee('border-radius: 999px; object-fit: cover', false);
    }

    public function test_ポスト詳細のポストとリプライの投稿者に画像が表示される(): void
    {
        $postAuthorUrl = $this->setAvatar($this->other);
        $post = Post::create([
            'user_id' => $this->other->id,
            'category_id' => Category::create(['name' => '雑記'])->id,
            'title' => 'タイトル',
            'content' => '本文',
        ]);
        $replierUrl = $this->setAvatar($this->me);
        Reply::create(['post_id' => $post->id, 'user_id' => $this->me->id, 'content' => 'リプライ']);

        $this->actingAs($this->me)->get(route('posts.show', $post))
            ->assertSee('src="'.$postAuthorUrl.'"', false)
            ->assertSee('src="'.$replierUrl.'"', false);
    }

    public function test_プロフィールに画像が表示される(): void
    {
        $url = $this->setAvatar($this->other);

        $this->actingAs($this->me)->get(route('users.show', $this->other))
            ->assertSee('src="'.$url.'"', false)
            ->assertSee('width: 96px; height: 96px', false);
    }

    public function test_リプライ一覧のアバターにも画像が表示される(): void
    {
        $url = $this->setAvatar($this->other);
        $post = Post::create([
            'user_id' => $this->me->id,
            'category_id' => Category::create(['name' => '雑記'])->id,
            'title' => 'タイトル',
            'content' => '本文',
        ]);
        Reply::create(['post_id' => $post->id, 'user_id' => $this->other->id, 'content' => 'リプライ']);

        $this->actingAs($this->me)->get(route('users.show', ['user' => $this->other, 'tab' => 'replies']))
            ->assertSee('src="'.$url.'"', false);
    }

    public function test_アバターを変えると過去のポストにも反映される(): void
    {
        Post::create([
            'user_id' => $this->me->id,
            'category_id' => Category::create(['name' => '雑記'])->id,
            'title' => '過去のポスト',
            'content' => '本文',
        ]);

        $url = $this->setAvatar($this->me);

        $this->actingAs($this->me)->get(route('posts.index'))
            ->assertSee('src="'.$url.'"', false);
    }

    public function test_画像を削除すると初期アイコンに戻る(): void
    {
        Post::create([
            'user_id' => $this->me->id,
            'category_id' => Category::create(['name' => '雑記'])->id,
            'title' => 'タイトル',
            'content' => '本文',
        ]);
        $this->setAvatar($this->me);

        $this->update(['remove_avatar' => '1']);

        $this->actingAs($this->me->fresh())->get(route('posts.index'))
            ->assertSee('>わ</div>', false)
            ->assertDontSee('<img class="avatar"', false);
    }
}
