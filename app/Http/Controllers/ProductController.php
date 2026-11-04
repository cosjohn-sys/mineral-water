<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status') ?: 'all';
        $perPage = $request->integer('per_page') ?: 10;
        $products = DB::table('products')
            ->select('id', 'name', 'price', 'stock_quantity', 'is_active')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($status !== 'all', function (Builder $query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search, 'status' => $status, 'per_page' => $perPage],
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Products/Form', ['product' => null]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $timestamp = now();
        $productId = DB::table('products')->insertGetId([
            ...$request->validated(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return Redirect::route('products.show', $productId)->with('status', 'Product added.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $product): Response
    {
        return Inertia::render('Products/Show', [
            'product' => DB::table('products')->where('id', $product)->firstOrFail(),
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $product): Response
    {
        return Inertia::render('Products/Form', [
            'product' => DB::table('products')->where('id', $product)->firstOrFail(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, int $product): RedirectResponse
    {
        DB::table('products')->where('id', $product)->firstOrFail();

        DB::table('products')->where('id', $product)->update([
            ...$request->validated(),
            'updated_at' => now(),
        ]);

        return Redirect::route('products.show', $product)->with('status', 'Product updated.');
    }

    /**
     * Deactivate the product while preserving its sale history.
     */
    public function destroy(int $product): RedirectResponse
    {
        DB::table('products')->where('id', $product)->firstOrFail();

        DB::table('products')->where('id', $product)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return Redirect::route('products.show', $product)->with('status', 'Product deactivated.');
    }
}
