<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutPage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'about_page';

    /**
     * Indicates if the model should be timestamped.
     * The about_page table only has updated_at, no created_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bio',
        'links',
        'extra_sections',
        'profile_photo',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'links' => 'array',
        'extra_sections' => 'array',
        'updated_at' => 'datetime',
    ];

    /**
     * Retrieve the singleton instance of the About page.
     * Creates an empty instance if none exists yet.
     */
    public static function singleton(): static
    {
        return static::firstOrNew([]);
    }
}
