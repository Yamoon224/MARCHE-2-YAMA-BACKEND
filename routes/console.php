<?php

use App\Modules\Inventory\Actions\ReleaseExpiredReservationsAction;
use App\Modules\Orders\Actions\ExpireUnpaidOrdersAction;
use App\Modules\Payments\Actions\RetryFailedRefundsAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:expire-unpaid', function (ExpireUnpaidOrdersAction $action) {
    $this->info($action->execute().' commande(s) expirée(s).');
})->purpose('Annule les commandes non payées dans le délai et libère leur stock');

Artisan::command('inventory:release-expired', function (ReleaseExpiredReservationsAction $action) {
    $this->info($action->execute().' réservation(s) libérée(s).');
})->purpose('Libère les réservations de stock expirées');

Artisan::command('payments:retry-refunds', function (RetryFailedRefundsAction $action) {
    $this->info($action->execute().' remboursement(s) repris avec succès.');
})->purpose("Relance les remboursements refusés par l'agrégateur ou restés en attente");

Schedule::command('payments:retry-refunds')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:release-expired')->everyFiveMinutes()->withoutOverlapping();
