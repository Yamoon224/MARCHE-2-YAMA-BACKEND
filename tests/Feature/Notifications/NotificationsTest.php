<?php

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\RetryFailedDeliveriesAction;
use App\Modules\Notifications\Actions\SendNotificationAction;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Support\Facades\DB;

/** Livraison payée en ligne menée jusqu'à la remise. @return array{customer: User, order_id: int, delivery: Delivery} */
function deliveredForNotifications(): array
{
    $ctx = readyDeliveryOrder('mobile_money');
    $courier = outForDelivery($ctx['delivery']);
    courierCall($courier, $ctx['delivery'], 'confirm', ['code' => $ctx['delivery']->confirmation_code, 'cash_collected_minor' => 0])->assertOk();

    return ['customer' => $ctx['customer'], 'order_id' => $ctx['order']->id, 'delivery' => $ctx['delivery']];
}

function notifTypes(User $user): array
{
    return UserNotification::where('user_id', $user->id)->orderBy('id')->pluck('type')->all();
}

it('notifies the customer at each key step of a delivery, once per step', function () {
    $ctx = deliveredForNotifications();

    expect(notifTypes($ctx['customer']))->toBe(['order_confirmed', 'delivery_started', 'order_delivered']);

    // Chaque notification part par SMS et par e-mail.
    $first = UserNotification::where('user_id', $ctx['customer']->id)->firstOrFail();
    expect($first->deliveries()->pluck('medium')->sort()->values()->all())->toBe(['email', 'sms'])
        ->and($first->deliveries()->where('status', 'sent')->count())->toBe(2);
});

it('does not notify twice when an event is replayed', function () {
    $ctx = deliveredForNotifications();
    $before = UserNotification::count();

    event(new OrderStatusChanged($ctx['order_id'], OrderStatus::Ready, OrderStatus::Delivered));

    expect(UserNotification::count())->toBe($before);
});

it('tells the customer when they are refunded after a cancellation', function () {
    $ctx = awaitingPaymentOrder(2, 5000);
    payOrder($ctx);

    cancelOrder($ctx['orderId'])->assertOk();

    $types = notifTypes($ctx['customer']);
    expect($types)->toContain('order_cancelled')->and($types)->toContain('refund_sent');

    $refund = UserNotification::where('user_id', $ctx['customer']->id)->where('type', 'refund_sent')->firstOrFail();
    expect($refund->body)->toContain('10 000 F CFA');
});

it('stores the recipient encrypted and never exposes it', function () {
    $ctx = deliveredForNotifications();
    $delivery = NotificationDelivery::where('medium', 'sms')->firstOrFail();

    expect(DB::table('notification_deliveries')->where('id', $delivery->id)->value('recipient'))->not->toContain(substr((string) $ctx['customer']->phone, -8))
        ->and($delivery->recipient)->toBe($ctx['customer']->phone)
        ->and($delivery->toArray())->not->toHaveKey('recipient');
});

it('keeps the in-app notification and retries later when the provider is down', function () {
    config(['notifications.fail_sends' => true]);
    $ctx = readyDeliveryOrder('mobile_money'); // la confirmation notifie pendant la panne

    $notification = UserNotification::where('user_id', $ctx['customer']->id)->where('type', 'order_confirmed')->firstOrFail();
    expect($notification->deliveries()->where('status', 'failed')->count())->toBe(2)
        ->and($notification->deliveries()->first()->last_error)->toContain('indisponible');

    // Le fil dans l'application, lui, est disponible tout de suite.
    $this->actingAs($ctx['customer'], 'sanctum')->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.type', 'order_confirmed');

    config(['notifications.fail_sends' => false]);
    expect(app(RetryFailedDeliveriesAction::class)->execute())->toBeGreaterThanOrEqual(2)
        ->and($notification->deliveries()->where('status', 'sent')->count())->toBe(2);
});

it('stops retrying after the maximum number of attempts', function () {
    config(['notifications.fail_sends' => true, 'notifications.max_attempts' => 3]);
    $ctx = readyDeliveryOrder('mobile_money');

    for ($i = 0; $i < 6; $i++) {
        app(RetryFailedDeliveriesAction::class)->execute();
    }

    expect(NotificationDelivery::where('user_notification_id', UserNotification::where('user_id', $ctx['customer']->id)->value('id'))->max('attempts'))->toBe(3);
});

it('never blocks the order when the provider is not configured for production-like use', function () {
    config(['notifications.driver' => 'inconnu']);
    $ctx = readyDeliveryOrder('mobile_money');

    expect(UserNotification::where('user_id', $ctx['customer']->id)->count())->toBeGreaterThan(0)
        ->and(NotificationDelivery::where('status', 'sent')->count())->toBe(0)
        ->and(NotificationDelivery::where('status', 'failed')->count())->toBeGreaterThan(0)
        ->and($ctx['order']->refresh()->status->value)->toBe('ready');
});

it('skips a channel when the customer left no contact for it', function () {
    $user = User::factory()->create(['phone' => null]);

    $notification = app(SendNotificationAction::class)->execute($user->id, 'order_confirmed', ['order_id' => 7], 'test:no-phone');

    expect($notification?->deliveries()->pluck('medium')->all())->toBe(['email']);
    expect(app(SendNotificationAction::class)->execute(999999, 'order_confirmed', ['order_id' => 7], 'test:ghost'))->toBeNull();
});

it('rejects an unknown notification type', function () {
    $user = User::factory()->create();

    expect(fn () => app(SendNotificationAction::class)->execute($user->id, 'inconnu', [], 'test:unknown'))->toThrow(InvalidArgumentException::class);
});

it('lists only my notifications, counts unread ones and marks them as read', function () {
    $ctx = deliveredForNotifications();
    $other = User::factory()->create();
    app(SendNotificationAction::class)->execute($other->id, 'order_confirmed', ['order_id' => 1], 'test:other');

    $response = $this->actingAs($ctx['customer'], 'sanctum')->getJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('meta.unread_count', 3)->assertJsonCount(3, 'data')->assertJsonPath('data.0.read', false);
    $id = $response->json('data.0.id');
    $otherId = UserNotification::where('user_id', $other->id)->value('id');

    $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
    $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 2);

    // Celle d'un autre client n'existe pas pour moi.
    $this->postJson("/api/v1/notifications/{$otherId}/read")->assertNotFound();
    expect(UserNotification::find($otherId)->read_at)->toBeNull();

    $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.marked', 2);
    $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 0);
});

it('requires authentication', function () {
    asGuest();
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
});
