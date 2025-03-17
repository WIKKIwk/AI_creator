<?php

namespace App\Models;

use App\Enums\LearnStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $word
 * @property string $language
 * @property string $meaning
 * @property string $synonyms
 * @property string $antonyms
 * @property LearnStatus $learn_status
 * @property int $learned_times
 * @property boolean $is_favorite
 * @property int $category_id
 *
 * @property Category $category
 */
class Word extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'learn_status' => LearnStatus::class,
        'is_favorite' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
