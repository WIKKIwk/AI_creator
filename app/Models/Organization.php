<?php

namespace App\Models;

use App\Enums\OrganizationType;
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
 * @property OrganizationType $type
 */
class Organization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => OrganizationType::class,
        'status' => OrganizationStatus::class,
    ];

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }
}
