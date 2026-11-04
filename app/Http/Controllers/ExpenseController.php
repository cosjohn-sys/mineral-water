<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'category' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $dateFrom = $request->input('date_from') ?: '';
        $dateTo = $request->input('date_to') ?: '';
        $category = $request->input('category') ?: '';
        $perPage = $request->integer('per_page') ?: 10;
        $expenses = DB::table('expenses')
            ->when($dateFrom !== '', function (Builder $query) use ($dateFrom): void {
                $query->where('expense_date', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function (Builder $query) use ($dateTo): void {
                $query->where('expense_date', '<=', $dateTo);
            })
            ->when($category !== '', function (Builder $query) use ($category): void {
                $query->where('category', $category);
            });

        return Inertia::render('Expenses/Index', [
            'totalExpenses' => (int) (clone $expenses)->sum(DB::raw('ROUND(amount * 100)')) / 100,
            'expenses' => $expenses->orderByDesc('expense_date')->orderByDesc('id')->paginate($perPage)->withQueryString(),
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'category' => $category, 'per_page' => $perPage],
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Expenses/Form', ['expense' => null, 'today' => now()->toDateString()]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $timestamp = now();
        $expenseId = DB::table('expenses')->insertGetId([
            ...$request->validated(), 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);

        return Redirect::route('expenses.show', $expenseId)->with('status', 'Expense added.');
    }

    public function show(int $expense): Response
    {
        return Inertia::render('Expenses/Show', [
            'expense' => DB::table('expenses')->where('id', $expense)->firstOrFail(), 'status' => session('status'),
        ]);
    }

    public function edit(int $expense): Response
    {
        return Inertia::render('Expenses/Form', [
            'expense' => DB::table('expenses')->where('id', $expense)->firstOrFail(), 'today' => now()->toDateString(),
        ]);
    }

    public function update(ExpenseRequest $request, int $expense): RedirectResponse
    {
        DB::table('expenses')->where('id', $expense)->firstOrFail();
        DB::table('expenses')->where('id', $expense)->update([...$request->validated(), 'updated_at' => now()]);

        return Redirect::route('expenses.show', $expense)->with('status', 'Expense updated.');
    }

    public function destroy(int $expense): RedirectResponse
    {
        DB::table('expenses')->where('id', $expense)->firstOrFail();
        DB::table('expenses')->where('id', $expense)->delete();

        return Redirect::route('expenses.index')->with('status', 'Expense deleted.');
    }
}
