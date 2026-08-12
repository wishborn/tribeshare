<?php

namespace App\Models;

use App\Enums\UnitReportStatus;
use Database\Factories\BookingUnitReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property UnitReportStatus $status
 * @property string|null $ledger_entry_id
 * @property int $suggested_charge_cents
 * @property int|null $final_charge_cents
 */
class BookingUnitReport extends Model
{
    /** @use HasFactory<BookingUnitReportFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => UnitReportStatus::class,
            'quantities' => 'array',
            'final_quantities' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The entry that actually billed the metered usage.
     *
     * @return BelongsTo<LedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    /**
     * True when the report was accepted but never billed — the defect this
     * schema exists to make impossible. Useful as a guard in tests.
     */
    public function isApprovedButUnbilled(): bool
    {
        return $this->status === UnitReportStatus::Approved
            && $this->ledger_entry_id === null;
    }
}
