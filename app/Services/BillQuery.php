<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Server-side search, filtering, sorting and pagination for the bill list.
 * Only allow-listed sort columns are accepted.
 */
class BillQuery
{
    public const SORTABLE = [
        'issued' => 'issued_at',
        'bill_number' => 'bill_number',
        'patient' => 'patient_name',
        'total' => 'total',
        'balance' => 'balance',
        'status' => 'status',
    ];

    public const SORT_LABELS = [
        'issued' => 'Bill date',
        'bill_number' => 'Bill no.',
        'patient' => 'Patient name',
        'total' => 'Total',
        'balance' => 'Balance due',
        'status' => 'Status',
    ];

    public const STATUS_FILTERS = [
        'due' => 'Payment due',
        'unpaid' => 'Unpaid',
        'partial' => 'Partly paid',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $sort = self::SORTABLE[$filters['sort'] ?? ''] ?? 'issued_at';
        $direction = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->query($filters)
            ->with(['report:id,report_number'])
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction) // stable pagination
            ->paginate($perPage)
            ->withQueryString();
    }

    public function query(array $filters): Builder
    {
        $query = Bill::query();

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('bill_number', 'like', $like)
                    ->orWhere('patient_name', 'like', $like)
                    ->orWhereHas('report', fn (Builder $r) => $r->where('report_number', 'like', $like))
                    ->orWhereHas('patient', fn (Builder $p) => $p->where('phone', 'like', $like)->orWhere('patient_code', 'like', $like));
            });
        }

        match ($filters['status'] ?? null) {
            'due' => $query->whereIn('status', [Bill::STATUS_UNPAID, Bill::STATUS_PARTIAL]),
            'unpaid', 'partial', 'paid', 'cancelled' => $query->where('status', $filters['status']),
            default => null,
        };

        if ($from = $this->date($filters['from'] ?? null)) {
            $query->where('issued_at', '>=', $from->startOfDay());
        }
        if ($to = $this->date($filters['to'] ?? null)) {
            $query->where('issued_at', '<=', $to->endOfDay());
        }

        if (! empty($filters['patient'])) {
            $query->where('patient_id', (int) $filters['patient']);
        }

        return $query;
    }

    private function date(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
