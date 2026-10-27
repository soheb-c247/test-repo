<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    protected $table = 'lab_bill_items';

    protected $fillable = [
        'report_item_id',
        'test_code',
        'description',
        'amount',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
