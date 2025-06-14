<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $phone
 * @property string $email
 * @property OrganizationStatus $status
 */
class Organization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => OrganizationStatus::class,
    ];

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }
}
