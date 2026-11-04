<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentReversalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class PaymentReversalController extends Controller
{
    public function store(PaymentReversalRequest $request, int $payment): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;
        $saleId = DB::table('payments')->where('id', $payment)->value('sale_id');
        abort_if($saleId === null, 404);

        $created = DB::transaction(function () use ($payment, $saleId, $data, $userId): bool {
            DB::table('sales')->where('id', $saleId)->lockForUpdate()->firstOrFail();
            $paymentDetails = DB::table('payments')->where('id', $payment)->lockForUpdate()->firstOrFail();

            if (DB::table('payment_reversals')->where('payment_id', $payment)->lockForUpdate()->exists()) {
                return false;
            }

            $timestamp = now();
            if ($paymentDetails->amount <= 0 || ! in_array($paymentDetails->payment_method, ['cash', 'gcash'], true) || $paymentDetails->paid_at > $timestamp->toDateTimeString()) {
                throw ValidationException::withMessages(['reason' => 'Only a valid payment already received can be reversed.']);
            }

            DB::table('payment_reversals')->insert([
                'payment_id' => $payment, 'user_id' => $userId, 'reason' => $data['reason'],
                'reversed_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]);

            return true;
        }, 3);

        return Redirect::route('sales.show', $saleId)->with('status', $created ? 'Full payment reversal recorded.' : 'Payment already reversed.');
    }
}
