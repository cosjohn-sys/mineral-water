<?php

namespace App\Http\Controllers;

use App\Http\Requests\GallonExchangeRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GallonExchangeController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $dateFrom = $request->input('date_from') ?: '';
        $dateTo = $request->input('date_to') ?: '';
        $perPage = $request->integer('per_page') ?: 10;
        $exchangeQuery = DB::table('gallon_exchanges')->join('sales', 'sales.id', '=', 'gallon_exchanges.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->when($dateFrom !== '', function (Builder $query) use ($dateFrom): void {
                $query->where('sales.sold_at', '>=', $dateFrom.' 00:00:00');
            })
            ->when($dateTo !== '', function (Builder $query) use ($dateTo): void {
                $query->where('sales.sold_at', '<=', $dateTo.' 23:59:59');
            });
        $summary = (clone $exchangeQuery)->where('sales.status', 'completed')
            ->selectRaw('COUNT(*) AS sale_count, COALESCE(SUM(gallon_exchanges.filled_gallons_out), 0) AS filled_gallons_out, COALESCE(SUM(gallon_exchanges.empty_gallons_returned), 0) AS empty_gallons_returned')
            ->selectRaw('COALESCE(SUM(CAST(gallon_exchanges.filled_gallons_out AS DECIMAL(20, 0)) - CAST(gallon_exchanges.empty_gallons_returned AS DECIMAL(20, 0))), 0) AS net_gallons_out')
            ->first();

        return Inertia::render('GallonExchanges/Index', [
            'exchanges' => $exchangeQuery
                ->select('gallon_exchanges.id', 'gallon_exchanges.sale_id', 'gallon_exchanges.filled_gallons_out', 'gallon_exchanges.empty_gallons_returned', 'gallon_exchanges.updated_at', 'sales.sale_number', 'sales.sale_type', 'sales.sold_at', 'sales.status', 'customers.name as customer_name')
                ->orderByDesc('sales.sold_at')->orderByDesc('gallon_exchanges.id')->paginate($perPage)->withQueryString(),
            'summary' => $summary,
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'per_page' => $perPage],
            'timezone' => config('app.timezone'),
        ]);
    }

    public function update(GallonExchangeRequest $request, int $sale): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($sale, $data): void {
            $saleDetails = DB::table('sales')->where('id', $sale)->lockForUpdate()->firstOrFail();

            if ($saleDetails->status !== 'completed') {
                throw ValidationException::withMessages(['gallon_exchange' => 'Gallon records for cancelled sales are read-only.']);
            }

            $timestamp = now();
            DB::table('gallon_exchanges')->updateOrInsert(
                ['sale_id' => $sale],
                fn (bool $exists): array => [
                    'filled_gallons_out' => (int) $data['filled_gallons_out'],
                    'empty_gallons_returned' => (int) $data['empty_gallons_returned'],
                    'updated_at' => $timestamp,
                    ...($exists ? [] : ['created_at' => $timestamp]),
                ],
            );
        }, 3);

        return Redirect::route('sales.show', $sale)->with('status', 'Gallon totals saved.');
    }
}
