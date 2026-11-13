<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\Contracts\PosPaymentRecorder;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Exceptions\PaymentNotAllowed;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentsPosRecorder implements PosPaymentRecorder
{
    public function __construct(private readonly OrderPaymentPort $orders) {}

    public function record(int $orderId, string $method, int $amountMinor): int
    {
        $order = $this->orders->findPayable($orderId);

        // Le montant encaissé doit être exactement celui de la commande : le caissier ne le fixe pas.
        if ($order === null || ! $order->acceptsPayment || $order->totalMinor !== $amountMinor) {
            throw PaymentNotAllowed::orderNotFound();
        }

        $payment = DB::transaction(function () use ($orderId, $method, $amountMinor, $order) {
            $payment = Payment::create([
                'reference' => 'PAY-'.Str::upper((string) Str::ulid()), 'order_id' => $orderId, 'provider' => 'pos',
                'method' => PaymentMethod::from($method), 'status' => PaymentStatus::Succeeded,
                'amount_minor' => $amountMinor, 'currency' => $order->currency,
                'note' => 'Encaissé au comptoir',
            ]);

            if (! $this->orders->markPaid($orderId)) {
                throw PaymentNotAllowed::orderNotFound();
            }

            return $payment;
        });

        PaymentSucceeded::dispatch($payment->id, $orderId, $amountMinor, $payment->currency, $payment->method);

        return $payment->id;
    }
}
