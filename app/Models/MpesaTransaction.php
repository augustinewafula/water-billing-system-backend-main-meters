<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MpesaTransaction extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $keyType = 'uuid';

    protected $fillable = [
        'FirstName',
        'MiddleName',
        'LastName',
        'TransactionType',
        'TransID',
        'TransTime',
        'BusinessShortCode',
        'BillRefNumber',
        'InvoiceNumber',
        'ThirdPartyTransID',
        'MSISDN',
        'TransAmount',
        'OrgAccountBalance',
        'Consumed'];

    protected $appends = ['transferable'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getTransferableAttribute(): bool
    {
        if (!array_key_exists('id', $this->attributes)) {
            return false;
        }
        return MeterToken::where('mpesa_transaction_id', $this->attributes['id'])
                ->get()
                ->count() === 0;
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subMonth());
    }
}
