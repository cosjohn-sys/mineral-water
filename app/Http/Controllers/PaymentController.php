<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, int $sale): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;
        $submissionToken = Str::lower($data['submission_token']);

        $created = DB::transaction(function () use ($data, $userId, $sale, $submissionToken): bool {
            $saleDetails = DB::table('sales')->where('id', $sale)->lockForUpdate()->firstOrFail();
            $existingPaymentId = DB::table('payments')->where('sale_id', $sale)
                ->where('submission_token', $submissionToken)->lockForUpdate()->value('id');

            if ($existingPaymentId !== null) {
                return false;
            }

            if ($saleDetails->status !== 'completed') {
                throw ValidationException::withMessages(['amount' => 'Payments can only be recorded for completed sales.']);
            }

            $timestamp = now();
            if (DB::table('payments')->where('sale_id', $sale)->where('amount', '>', 0)
                ->whereIn('payment_method', ['cash', 'gcash'])->where('paid_at', '>', $timestamp)->exists()) {
                throw ValidationException::withMessages(['amount' => 'This sale has a future-dated payment. Correct that record before adding another payment.']);
            }

            $totalParts = explode('.', (string) $saleDetails->total_amount, 2);
            $totalCents = (int) $totalParts[0] * 100 + (int) str_pad($totalParts[1] ?? '', 2, '0');
            $paidCents = (int) DB::table('payments')->where('sale_id', $sale)
                ->where('amount', '>', 0)->whereIn('payment_method', ['cash', 'gcash'])->where('paid_at', '<=', $timestamp)
                ->whereNotExists(function (Builder $query) use ($timestamp): void {
                    $query->selectRaw('1')->from('payment_reversals')->whereColumn('payment_id', 'payments.id')
                        ->whereColumn('reversed_at', '>=', 'payments.paid_at')->where('reversed_at', '<=', $timestamp);
                })
                ->sum(DB::raw('ROUND(amount * 100)'));
            $amountParts = explode('.', (string) $data['amount'], 2);
            $amountCents = (int) $amountParts[0] * 100 + (int) str_pad($amountParts[1] ?? '', 2, '0');

            if ($amountCents > $totalCents - $paidCents) {
                throw ValidationException::withMessages(['amount' => 'The payment amount must not exceed the outstanding balance.']);
            }

            DB::table('payments')->insert([
                'sale_id' => $sale,
                'user_id' => $userId,
                'submission_token' => $submissionToken,
                'amount' => sprintf('%d.%02d', intdiv($amountCents, 100), $amountCents % 100),
                'payment_method' => $data['payment_method'],
                'paid_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return true;
        }, 3);

        return Redirect::route('sales.show', $sale)->with('status', $created ? 'Payment recorded.' : 'Payment already recorded.');
    }
}
