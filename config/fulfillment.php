<?php

return [
    // Marge de pesée acceptée au-delà de la quantité commandée pour les produits au poids : le client n'est
    // jamais débité de plus que cette marge (pré-autorisation du cahier des charges : +10 %).
    'weight_margin_percent' => (int) env('FULFILLMENT_WEIGHT_MARGIN_PERCENT', 10),
];
