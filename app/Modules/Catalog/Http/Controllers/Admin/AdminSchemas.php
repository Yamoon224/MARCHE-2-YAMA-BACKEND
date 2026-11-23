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
#[OA\Schema(schema: 'MediaUpload', required: ['url'], properties: [new OA\Property(property: 'url', type: 'string')])]
final class AdminSchemas {}
