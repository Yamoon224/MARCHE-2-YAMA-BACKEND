<?php

declare(strict_types=1);

namespace App\Modules\Claims\Actions;

use App\Modules\Claims\Enums\ClaimIssue;
use App\Modules\Claims\Enums\ClaimStatus;
use App\Modules\Claims\Exceptions\ClaimNotAllowed;
use App\Modules\Claims\Models\Claim;
use App\Modules\Orders\Contracts\OrderClaimPort;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class OpenClaimAction
{
    public function __construct(private readonly OrderClaimPort $orders) {}

    /**
     * Ouvre une réclamation sur une commande livrée, par son propriétaire, dans le délai imparti.
     * Les quantités sont contrôlées contre ce que le client a réellement reçu, et une photo est exigée
     * sauf pour un article simplement manquant. La commande passe « en réclamation ».
     *
     * @param  list<array{order_line_id: int, issue: string, quantity: int, note?: string|null}>  $items
     * @param  list<UploadedFile>  $photos
     *
     * @throws ClaimNotAllowed
     */
    public function execute(int $userId, int $orderId, array $items, array $photos, ?string $description): Claim
    {
        $order = $this->orders->find($orderId);

        // Même réponse pour « inexistante » et « à un autre client » : pas d'énumération possible.
        if ($order === null || $order->userId !== $userId) {
            throw ClaimNotAllowed::orderNotFound();
        }

        if ($order->status !== 'delivered') {
            throw Claim::query()->where('order_id', $orderId)->exists() ? ClaimNotAllowed::alreadyClaimed() : ClaimNotAllowed::notDelivered();
        }

        $windowHours = (int) config('claims.window_hours');
        if ($order->deliveredAt === null || $order->deliveredAt->addHours($windowHours)->isPast()) {
            throw ClaimNotAllowed::windowExpired($windowHours);
        }

        if (Claim::where('order_id', $orderId)->exists()) {
            throw ClaimNotAllowed::alreadyClaimed();
        }

        $lines = collect($order->lines)->keyBy('orderLineId');
        $seen = [];
        $rows = [];
        $needsPhoto = false;

        foreach ($items as $item) {
            $line = $lines->get($item['order_line_id']) ?? throw ClaimNotAllowed::unknownLine();

            if (isset($seen[$line->orderLineId])) {
                throw ClaimNotAllowed::duplicateLine();
            }
            $seen[$line->orderLineId] = true;

            if ($item['quantity'] < 1 || $item['quantity'] > $line->quantity) {
                throw ClaimNotAllowed::quantityTooHigh($line->name, $line->quantity);
            }

            $issue = ClaimIssue::from($item['issue']);
            $needsPhoto = $needsPhoto || $issue->requiresPhoto();

            $rows[] = [
                'order_line_id' => $line->orderLineId, 'product_id' => $line->productId, 'name' => $line->name,
                'issue' => $issue, 'quantity' => $item['quantity'], 'note' => $item['note'] ?? null,
                // Plafond : valeur proportionnelle de la quantité réclamée sur ce que le client a payé pour la ligne.
                'claimable_minor' => intdiv($line->lineTotalMinor * $item['quantity'], $line->quantity),
            ];
        }

        if ($needsPhoto && $photos === []) {
            throw ClaimNotAllowed::photoRequired();
        }

        $stored = [];

        try {
            $claim = DB::transaction(function () use ($userId, $orderId, $rows, $photos, $description, &$stored) {
                $claim = Claim::create([
                    'order_id' => $orderId, 'user_id' => $userId, 'status' => ClaimStatus::Open, 'description' => $description,
                ]);
                $claim->items()->createMany($rows);

                foreach ($photos as $photo) {
                    $path = $photo->store("claims/{$claim->id}", config('claims.disk'));
                    $stored[] = $path;
                    $claim->photos()->create(['path' => $path, 'mime' => (string) $photo->getMimeType(), 'size' => (int) $photo->getSize()]);
                }

                try {
                    $this->orders->markClaimOpened($orderId, $userId, 'Réclamation du client');
                } catch (InvalidOrderTransition) {
                    throw ClaimNotAllowed::notDelivered();
                }

                return $claim;
            });
        } catch (UniqueConstraintViolationException) {
            $this->discard($stored);

            throw ClaimNotAllowed::alreadyClaimed();
        } catch (\Throwable $e) {
            // Rien ne reste sur le disque si la réclamation n'a pas pu être enregistrée.
            $this->discard($stored);

            throw $e;
        }

        return $claim->load(['items', 'photos']);
    }

    /** @param  list<string>  $paths */
    private function discard(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk((string) config('claims.disk'))->delete($paths);
        }
    }
}
