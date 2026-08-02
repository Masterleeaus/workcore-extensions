<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

/**
 * @deprecated Inspection ownership has moved to QualityControl.
 *             Use Modules\QualityControl\Entities\QcRecord instead.
 *             This model and its underlying table (pm_property_inspections) are
 *             retained for read-only backward compatibility only and will be retired
 *             once backfill parity is confirmed. Do NOT write new rows via this model.
 */
class PropertyInspection extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'pm_property_inspections';

    /**
     * Boot: emit a deprecation log warning whenever a write is attempted through
     * this model so that stale call-sites surface in logs before the table is dropped.
     */
    protected static function booted(): void
    {
        $warn = static function (): void {
            Log::warning('[DEPRECATED] Write attempted on pm_property_inspections via PropertyInspection model. ' .
                'Inspection ownership has moved to QualityControl (qc_records). ' .
                'This write path will be removed in a future release.', [
                    'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6))
                        ->map(fn ($f) => ($f['class'] ?? '') . '::' . ($f['function'] ?? ''))
                        ->implode(' → '),
                ]);
        };

        static::creating($warn);
        static::updating($warn);
        static::deleting($warn);
    }

    protected $fillable = [
        'company_id','property_id','inspection_type','scheduled_for','inspected_by',
        'status','score','findings','actions','completed_at'
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'completed_at' => 'datetime',
        'findings' => 'array',
        'actions' => 'array',
    ];

    public function property(){ return $this->belongsTo(Property::class, 'property_id'); }
}
