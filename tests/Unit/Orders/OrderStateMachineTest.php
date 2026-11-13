<?php

use App\Modules\Orders\Domain\OrderStateMachine;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;

/** Transitions autorisées, telles que listées en section 3.2 du cahier des charges. */
dataset('allowed transitions', [
    [OrderStatus::Draft, OrderStatus::AwaitingPayment],
    [OrderStatus::AwaitingPayment, OrderStatus::Paid],
    [OrderStatus::AwaitingPayment, OrderStatus::PaymentFailed],
    [OrderStatus::PaymentFailed, OrderStatus::AwaitingPayment],
    [OrderStatus::Paid, OrderStatus::Confirmed],
    [OrderStatus::Paid, OrderStatus::UnderReview],
    [OrderStatus::UnderReview, OrderStatus::Confirmed],
    [OrderStatus::Confirmed, OrderStatus::Preparing],
    [OrderStatus::Preparing, OrderStatus::Ready],
    [OrderStatus::Ready, OrderStatus::Assigned],
    [OrderStatus::Ready, OrderStatus::Delivered],
    [OrderStatus::Assigned, OrderStatus::OutForDelivery],
    [OrderStatus::OutForDelivery, OrderStatus::Delivered],
    [OrderStatus::OutForDelivery, OrderStatus::DeliveryFailed],
    [OrderStatus::DeliveryFailed, OrderStatus::OutForDelivery],
    [OrderStatus::Delivered, OrderStatus::Completed],
    [OrderStatus::Delivered, OrderStatus::Claim],
    [OrderStatus::Claim, OrderStatus::Refunded],
]);

it('allows every listed transition', function (OrderStatus $from, OrderStatus $to) {
    $reason = $to->requiresReason() ? 'motif' : null;

    (new OrderStateMachine)->assertCanTransition($from, $to, $reason);

    expect(true)->toBeTrue();
})->with('allowed transitions');

it('refuses every transition that is not listed', function () {
    $machine = new OrderStateMachine;
    $listed = 0;

    foreach (OrderStatus::cases() as $from) {
        foreach (OrderStatus::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $listed++;

                continue;
            }

            expect(fn () => $machine->assertCanTransition($from, $to, 'motif'))
                ->toThrow(InvalidOrderTransition::class);
        }
    }

    // 18 transitions du dataset ci-dessus + « brouillon -> confirmée » + 7 annulations
    // (brouillon, attente de paiement, paiement échoué, payée, en vérification, confirmée, en préparation)
    // + « échec de livraison -> retournée » + « réclamation -> terminée (refusée) ».
    expect($listed)->toBe(18 + 1 + 7 + 1 + 1);
});

it('has no way out of terminal states', function (OrderStatus $status) {
    expect($status->isTerminal())->toBeTrue()->and($status->allowedTransitions())->toBe([]);
})->with([OrderStatus::Completed, OrderStatus::Refunded, OrderStatus::Returned, OrderStatus::Cancelled]);

it('never allows a transition into the same status', function (OrderStatus $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with(OrderStatus::cases());

it('requires a reason for cancellation, claim, delivery failure and return', function (OrderStatus $from, OrderStatus $to) {
    $machine = new OrderStateMachine;

    expect(fn () => $machine->assertCanTransition($from, $to, null))->toThrow(InvalidOrderTransition::class)
        ->and(fn () => $machine->assertCanTransition($from, $to, '   '))->toThrow(InvalidOrderTransition::class);
})->with([
    [OrderStatus::Confirmed, OrderStatus::Cancelled],
    [OrderStatus::Delivered, OrderStatus::Claim],
    [OrderStatus::OutForDelivery, OrderStatus::DeliveryFailed],
    [OrderStatus::DeliveryFailed, OrderStatus::Returned],
]);

it('cannot cancel an order already handed to the courier', function (OrderStatus $from) {
    expect($from->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
})->with([OrderStatus::Assigned, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Completed]);
