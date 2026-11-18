<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/** Page de présentation du backend (racine du serveur). */
class HomeController extends Controller
{
    /** Rôle de chaque module métier, dans l'ordre du parcours d'une commande. */
    private const MODULES = [
        'Identity' => ['Comptes et rôles', 'Clients, personnel, connexion, rôles et permissions.'],
        'Catalog' => ['Catalogue', 'Rayons, produits et galeries d\'images, bannières, recettes.'],
        'Inventory' => ['Stock', 'Lots, péremptions, mouvements et rappels de lots.'],
        'Orders' => ['Commandes', 'Panier, machine à états, historique et détails.'],
        'Payments' => ['Paiements', 'Mobile money, carte, espèces, remboursements.'],
        'Fulfillment' => ['Préparation', 'Listes de picking, pesée et remplacements.'],
        'Delivery' => ['Livraison', 'Zones, créneaux, tournées, code de confirmation, caisse livreur.'],
        'Claims' => ['Litiges', 'Réclamations clients avec photos et décisions.'],
        'Invoicing' => ['Facturation', 'Factures et avoirs immuables.'],
        'Accounting' => ['Comptabilité', 'Grand livre en écriture seule et balance.'],
        'Pos' => ['Caisse', 'Ventes au comptoir.'],
        'Notifications' => ['Notifications', 'Messages aux clients et journal.'],
        'Promotions' => ['Promotions', 'Codes promo : pourcentage ou montant fixe.'],
        'Audit' => ['Audit', 'Journal signé des actions du personnel.'],
        'Reporting' => ['Rapports', 'Tableau de bord et ventes exportables.'],
    ];

    public function __invoke(): View
    {
        $modules = [];

        foreach (self::MODULES as $folder => [$name, $description]) {
            if (is_dir(app_path('Modules/'.$folder))) {
                $modules[] = ['name' => $name, 'description' => $description];
            }
        }

        return view('home', [
            'modules' => $modules,
            'version' => $this->apiVersion(),
            'database' => $this->databaseIsUp(),
            'environment' => app()->environment(),
            'storeUrl' => config('app.store_url'),
            'backofficeUrl' => config('app.backoffice_url'),
        ]);
    }

    private function apiVersion(): string
    {
        $file = storage_path('api-docs/api-docs.json');

        if (is_file($file)) {
            $json = json_decode((string) file_get_contents($file), true);

            if (is_array($json) && is_string($json['info']['version'] ?? null)) {
                return $json['info']['version'];
            }
        }

        return '1.0.0';
    }

    private function databaseIsUp(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
