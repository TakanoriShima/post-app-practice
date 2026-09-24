<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AvatarStorage
{
    /** 保存する画像の一辺（px） */
    public const SIZE = 256;

    private const DIRECTORY = 'avatars';

    /**
     * アップロードされた画像を、向きを補正し、中央を正方形に切り抜いて縮小し、
     * WebP で保存して、保存先のパス（public ディスク内）を返す。
     * 保存するのは加工後の画像だけなので、EXIF（位置情報など）は残らない。
     */
    public function store(UploadedFile $file): string
    {
        $this->ensureMemoryLimit();

        $path = $file->getRealPath();
        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            throw new RuntimeException('画像を読み込めませんでした。');
        }

        if ($file->getMimeType() === 'image/jpeg') {
            $source = $this->orient($source, $path);
        }

        $binary = $this->encode($this->cropAndResize($source));

        $stored = self::DIRECTORY.'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($stored, $binary);

        return $stored;
    }

    /** 保存済みのアバターを消す。avatars/ 配下以外のパスは触らない */
    public function delete(?string $path): void
    {
        if (filled($path) && str_starts_with($path, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /** スマホ写真の EXIF の向き（Orientation）を反映して、見た目どおりの向きにする */
    private function orient(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        // imagerotate は反時計回りの角度
        $angle = match ($orientation) {
            3 => 180,
            6, 7 => -90,
            5, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                return $rotated;
            }
        }

        return $image;
    }

    /** 中央を正方形に切り抜いて SIZE×SIZE に縮小する（透過は保つ） */
    private function cropAndResize(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled(
            $canvas, $source,
            0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side,
        );

        return $canvas;
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        $ok = imagewebp($image, null, 85);
        $binary = (string) ob_get_clean();

        if (! $ok || $binary === '') {
            throw new RuntimeException('画像を保存できませんでした。');
        }

        return $binary;
    }

    /** 大きな画像（縦横 5000px まで）を GD で扱えるよう、メモリ上限が低い環境では引き上げる */
    private function ensureMemoryLimit(): void
    {
        $limit = ini_get('memory_limit');

        if ($limit === false || $limit === '-1') {
            return;
        }

        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        if ($bytes < 512 * 1024 ** 2) {
            ini_set('memory_limit', '512M');
        }
    }
}
