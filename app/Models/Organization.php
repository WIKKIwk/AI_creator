<?php

namespace App\Models;

use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $code
 * @property string $phone
 * @property string $email
 * @property OrganizationType $type
 */
class Organization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => OrganizationType::class,
    ];
}
