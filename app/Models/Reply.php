<?php

namespace App\Models;

use App\Models\Concerns\HasLikes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reply extends Model
{
    use HasFactory, HasLikes;

    protected $fillable = [
        'post_id',
        'user_id',
        'content',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** このリプライにいいねした人 */
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reply_likes')->withTimestamps();
    }
}
