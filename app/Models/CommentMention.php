<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentMention extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'comment_id',
        'mentioned_user_id',
    ];

    /**
     * The comment in which the mention was made.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * The user who was mentioned.
     */
    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
