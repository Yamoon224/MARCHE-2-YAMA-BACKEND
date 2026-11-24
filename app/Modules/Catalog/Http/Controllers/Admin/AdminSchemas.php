<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CategoryInput', required: ['name', 'slug'], properties: [
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'slug', type: 'string'),
    new OA\Property(property: 'icon', type: 'string', nullable: true),
    new OA\Property(property: 'image_url', type: 'string', nullable: true),
    new OA\Property(property: 'position', type: 'integer'),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'show_on_home', type: 'boolean'),
    new OA\Property(property: 'home_title', type: 'string', nullable: true),
    new OA\Property(property: 'home_subtitle', type: 'string', nullable: true),
])]
#[OA\Schema(schema: 'BannerInput', required: ['title', 'cta_label', 'cta_url'], properties: [
    new OA\Property(property: 'title', type: 'string'),
    new OA\Property(property: 'eyebrow', type: 'string', nullable: true),
    new OA\Property(property: 'text', type: 'string', nullable: true),
    new OA\Property(property: 'cta_label', type: 'string'),
    new OA\Property(property: 'cta_url', type: 'string'),
    new OA\Property(property: 'image_url', type: 'string', nullable: true),
    new OA\Property(property: 'position', type: 'integer'),
    new OA\Property(property: 'is_active', type: 'boolean'),
])]
#[OA\Schema(schema: 'RecipeInput', required: ['name', 'slug'], properties: [
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'slug', type: 'string'),
    new OA\Property(property: 'icon', type: 'string', nullable: true),
    new OA\Property(property: 'image_url', type: 'string', nullable: true),
    new OA\Property(property: 'position', type: 'integer'),
    new OA\Property(property: 'is_active', type: 'boolean'),
    new OA\Property(property: 'items', type: 'array', description: 'Ingrédients : remplace la liste existante si fournie', items: new OA\Items(
        required: ['product_id', 'quantity'],
        properties: [
            new OA\Property(property: 'product_id', type: 'integer'),
            new OA\Property(property: 'quantity', type: 'integer', description: 'Unité de base : pièces, grammes ou millilitres'),
        ],
        type: 'object',
    )),
])]
#[OA\Schema(schema: 'ProductUpdate', properties: [
    new OA\Property(property: 'category_id', type: 'integer'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'slug', type: 'string'),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'image_url', type: 'string', nullable: true),
    new OA\Property(property: 'price_minor', type: 'integer'),
    new OA\Property(property: 'compare_at_price_minor', type: 'integer', nullable: true),
    new OA\Property(property: 'is_featured', type: 'boolean'),
    new OA\Property(property: 'track_stock', type: 'boolean'),
    new OA\Property(property: 'is_active', type: 'boolean'),
])]
#[OA\Schema(schema: 'ProductImportRow', required: ['name'], properties: [
    new OA\Property(property: 'row', type: 'integer', description: 'Numéro de ligne dans le fichier (pour le compte rendu)'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'sku', type: 'string', nullable: true, description: 'Clé de rapprochement ; générée depuis external_id ou le nom si absente'),
    new OA\Property(property: 'external_id', type: 'string', nullable: true),
    new OA\Property(property: 'barcode', type: 'string', nullable: true),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'category', type: 'string', nullable: true, description: 'Nom du rayon (créé s’il n’existe pas)'),
    new OA\Property(property: 'sale_unit', type: 'string', enum: ['piece', 'kg', 'l'], nullable: true),
    new OA\Property(property: 'is_weight_variable', type: 'boolean', nullable: true),
    new OA\Property(property: 'price_minor', type: 'integer', nullable: true),
    new OA\Property(property: 'compare_at_price_minor', type: 'integer', nullable: true),
    new OA\Property(property: 'vat_rate_bp', type: 'integer', nullable: true),
    new OA\Property(property: 'track_stock', type: 'boolean', nullable: true),
    new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
    new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string'), description: 'URL d’images (10 au plus)'),
])]
#[OA\Schema(schema: 'ProductImportRequest', required: ['rows'], properties: [
    new OA\Property(property: 'rows', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductImportRow'), description: '5 000 lignes au plus'),
    new OA\Property(property: 'dry_run', type: 'boolean'),
])]
#[OA\Schema(schema: 'ProductImportReport', required: ['dry_run', 'created', 'updated', 'failed', 'categories_created', 'errors', 'warnings'], properties: [
    new OA\Property(property: 'dry_run', type: 'boolean'),
    new OA\Property(property: 'created', type: 'integer'),
    new OA\Property(property: 'updated', type: 'integer'),
    new OA\Property(property: 'failed', type: 'integer'),
    new OA\Property(property: 'categories_created', type: 'array', items: new OA\Items(type: 'string')),
    new OA\Property(property: 'errors', type: 'array', items: new OA\Items(required: ['row', 'name', 'messages'], properties: [
        new OA\Property(property: 'row', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'messages', type: 'array', items: new OA\Items(type: 'string')),
    ], type: 'object')),
    new OA\Property(property: 'warnings', type: 'array', items: new OA\Items(required: ['row', 'name', 'message'], properties: [
        new OA\Property(property: 'row', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'message', type: 'string'),
    ], type: 'object')),
])]
#[OA\Schema(schema: 'MediaUpload', required: ['url'], properties: [new OA\Property(property: 'url', type: 'string')])]
final class AdminSchemas {}
