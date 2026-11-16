<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

use App\Shared\Money;
use InvalidArgumentException;

/** Textes des notifications (français). Un type inconnu est une erreur de programmation, jamais un message vide. */
final class Templates
{
    public const ORDER_CONFIRMED = 'order_confirmed';

    public const DELIVERY_STARTED = 'delivery_started';

    public const ORDER_DELIVERED = 'order_delivered';

    public const DELIVERY_FAILED = 'delivery_failed';

    public const ORDER_CANCELLED = 'order_cancelled';

    public const REFUND_SENT = 'refund_sent';

    public const ORDER_READY_PICKUP = 'order_ready_pickup';

    public const LOT_RECALLED = 'lot_recalled';

    /**
     * @param  array{order_id?: int, amount_minor?: int, lot_code?: string, reason?: string}  $params
     * @return array{title: string, body: string}
     */
    public static function render(string $type, array $params): array
    {
        $order = $params['order_id'] ?? 0;

        return match ($type) {
            self::ORDER_CONFIRMED => ['title' => "Commande n°{$order} confirmée", 'body' => "Nous préparons votre commande n°{$order}. Merci de votre confiance !"],
            self::DELIVERY_STARTED => ['title' => "Votre livreur est en route (commande n°{$order})", 'body' => 'Suivez sa position dans votre espace. Gardez votre code de confirmation : il ne se donne qu\'au livreur, à la remise de la commande.'],
            self::ORDER_DELIVERED => ['title' => "Commande n°{$order} livrée", 'body' => 'Bon appétit ! En cas de problème, vous pouvez déposer une réclamation avec photos depuis votre commande.'],
            self::DELIVERY_FAILED => ['title' => "Livraison n°{$order} non aboutie", 'body' => 'Nous n\'avons pas pu vous remettre votre commande. Notre équipe vous recontacte pour convenir d\'une suite.'],
            self::ORDER_CANCELLED => ['title' => "Commande n°{$order} annulée", 'body' => "Votre commande n°{$order} a été annulée. Si vous aviez payé, vous êtes remboursé."],
            self::REFUND_SENT => ['title' => 'Remboursement effectué', 'body' => 'Nous vous avons remboursé '.self::money($params['amount_minor'] ?? 0)." pour la commande n°{$order}."],
            self::ORDER_READY_PICKUP => ['title' => "Commande n°{$order} prête au retrait", 'body' => "Votre commande n°{$order} vous attend en magasin. Munissez-vous de votre numéro de commande."],
            self::LOT_RECALLED => [
                'title' => "Rappel de produit — commande n°{$order}",
                'body' => "Un produit de votre commande n°{$order} (lot ".($params['lot_code'] ?? '?').') fait l\'objet d\'un rappel : '.mb_substr($params['reason'] ?? '', 0, 200).'. Ne le consommez pas et contactez-nous : nous vous remboursons.',
            ],
            default => throw new InvalidArgumentException("Type de notification inconnu : {$type}"),
        };
    }

    private static function money(int $minor): string
    {
        return Money::eur($minor);
    }
}
