<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * Cycle de vie d'une commande (cahier des charges, section 3.2).
 * Seules les transitions déclarées dans allowedTransitions() sont autorisées.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentFailed = 'payment_failed';
    case Paid = 'paid';
    case UnderReview = 'under_review';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Assigned = 'assigned';
    case OutForDelivery = 'out_for_delivery';
    case DeliveryFailed = 'delivery_failed';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Claim = 'claim';
    case Refunded = 'refunded';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::AwaitingPayment, self::Confirmed, self::Cancelled],
            self::AwaitingPayment => [self::Paid, self::PaymentFailed, self::Cancelled],
            self::PaymentFailed => [self::AwaitingPayment, self::Cancelled],
            self::Paid => [self::Confirmed, self::UnderReview, self::Cancelled],
            self::UnderReview => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            // Retrait en magasin : « prête » -> « retirée » (statut Delivered).
            self::Ready => [self::Assigned, self::Delivered],
            self::Assigned => [self::OutForDelivery],
            self::OutForDelivery => [self::Delivered, self::DeliveryFailed],
            self::DeliveryFailed => [self::OutForDelivery, self::Returned],
            self::Delivered => [self::Completed, self::Claim],
            // Réclamation : remboursée/avoir, ou refusée (retour à « terminée »).
            self::Claim => [self::Refunded, self::Completed],
            self::Completed, self::Refunded, self::Returned, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** Une transition vers ce statut exige un motif (annulation, réclamation, échec, retour). */
    public function requiresReason(): bool
    {
        return in_array($this, [self::Cancelled, self::Claim, self::DeliveryFailed, self::Returned], true);
    }
}
