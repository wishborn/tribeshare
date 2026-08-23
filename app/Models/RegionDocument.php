<?php

namespace App\Models;

use App\Enums\RegionDocumentCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A file in a region's library.
 *
 * @property RegionDocumentCategory $category
 */
class RegionDocument extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'disk' => 'local',
        'size_bytes' => 0,
    ];

    protected function casts(): array
    {
        return ['category' => RegionDocumentCategory::class];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsToMany<RegionClaim, $this> */
    public function claims(): BelongsToMany
    {
        return $this->belongsToMany(RegionClaim::class, 'region_claim_documents');
    }
}
