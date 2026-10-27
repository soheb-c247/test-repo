<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Server-side search, filtering, sorting and pagination for report lists.
 * Only allow-listed sort columns are accepted.
 */
class ReportQuery
{
    public const SORTABLE = [
        'report_number' => 'report_number',
        'patient' => 'patient_name',
        'registered' => 'registered_at',
        'collected' => 'collected_at',
        'reported' => 'reported_at',
        'status' => 'status',
        'created' => 'created_at',
    ];

    public const STATUS_FILTERS = [
        'draft' => 'Draft',
        'correction' => 'Under correction',
        'final' => 'Finalized',
        'flagged' => 'Has flagged results',
    ];

    /**
     * @param  array{q?:string,status?:string,from?:string,to?:string,category?:string,patient?:int,sort?:string,dir?:string}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $sort = self::SORTABLE[$filters['sort'] ?? ''] ?? 'created_at';
        $direction = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->query($filters)
            ->with(['items:id,report_id,test_name,category,position'])
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction) // stable pagination
            ->paginate($perPage)
            ->withQueryString();
    }

    public function query(array $filters): Builder
    {
        $query = Report::query();

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('report_number', 'like', $like)
                    ->orWhere('patient_name', 'like', $like)
                    ->orWhere('sample_id', 'like', $like)
                    ->orWhereHas('items', fn (Builder $items) => $items->where('test_name', 'like', $like)->orWhere('test_code', 'like', $like));
            });
        }

        match ($filters['status'] ?? null) {
            'draft' => $query->where('status', Report::STATUS_DRAFT)->where('revision', 0),
            'correction' => $query->where('status', Report::STATUS_DRAFT)->where('revision', '>', 0),
            'final' => $query->where('status', Report::STATUS_FINAL),
            'flagged' => $query->where('abnormal_count', '>', 0),
            default => null,
        };

        if ($from = $this->date($filters['from'] ?? null)) {
            $query->where('registered_at', '>=', $from->startOfDay());
        }
        if ($to = $this->date($filters['to'] ?? null)) {
            $query->where('registered_at', '<=', $to->endOfDay());
        }

        if (! empty($filters['category'])) {
            $query->whereHas('items', fn (Builder $items) => $items->where('category', $filters['category']));
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
