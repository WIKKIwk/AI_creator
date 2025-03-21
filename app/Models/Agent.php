<?php

namespace App\Models;

use App\Enums\PartnerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property PartnerType $type
 */
class Agent extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => PartnerType::class,
    ];
}
