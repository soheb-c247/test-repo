<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model
{
    protected $table = 'lab_bill_payments';

    public const METHODS = [
        'cash' => 'Cash',
        'upi' => 'UPI',
        'card' => 'Card',
        'bank' => 'Bank transfer',
        'other' => 'Other',
    ];

    protected $fillable = [
        'amount',
        'method',
        'reference',
        'paid_at',
        'note',
        'form_token',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
