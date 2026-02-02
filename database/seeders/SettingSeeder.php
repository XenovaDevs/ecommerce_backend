<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * @ai-context Seeder for default application settings.
 *             Creates the initial configuration for a new store.
 */
class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Store Information (Public)
            [
                'key' => 'store_name',
                'value' => 'Mi Tienda',
                'type' => 'string',
                'group' => 'store',
                'is_public' => true,
                'description' => 'Name of the store',
            ],
            [
                'key' => 'store_logo',
                'value' => null,
                'type' => 'string',
                'group' => 'store',
                'is_public' => true,
                'description' => 'Store logo URL',
            ],
            [
                'key' => 'store_favicon',
                'value' => null,
                'type' => 'string',
                'group' => 'store',
                'is_public' => true,
                'description' => 'Store favicon URL',
            ],
            [
                'key' => 'store_description',
                'value' => 'Tu tienda online de confianza',
                'type' => 'string',
                'group' => 'store',
                'is_public' => true,
                'description' => 'Store description for SEO',
            ],

            // Contact Information (Public)
            [
                'key' => 'store_email',
                'value' => 'contacto@mitienda.com',
                'type' => 'string',
                'group' => 'contact',
                'is_public' => true,
                'description' => 'Contact email',
            ],
            [
                'key' => 'store_phone',
                'value' => '+54 11 1234-5678',
                'type' => 'string',
                'group' => 'contact',
                'is_public' => true,
                'description' => 'Contact phone number',
            ],
            [
                'key' => 'store_whatsapp',
                'value' => null,
                'type' => 'string',
                'group' => 'contact',
                'is_public' => true,
                'description' => 'WhatsApp number for support',
            ],
            [
                'key' => 'store_address',
                'value' => null,
                'type' => 'string',
                'group' => 'contact',
                'is_public' => true,
                'description' => 'Physical store address',
            ],

            // Social Media (Public)
            [
                'key' => 'social_facebook',
                'value' => null,
                'type' => 'string',
                'group' => 'social',
                'is_public' => true,
                'description' => 'Facebook page URL',
            ],
            [
                'key' => 'social_instagram',
                'value' => null,
                'type' => 'string',
                'group' => 'social',
                'is_public' => true,
                'description' => 'Instagram profile URL',
            ],
            [
                'key' => 'social_twitter',
                'value' => null,
                'type' => 'string',
                'group' => 'social',
                'is_public' => true,
                'description' => 'Twitter/X profile URL',
            ],

            // Currency & Locale (Public)
            [
                'key' => 'currency',
                'value' => 'ARS',
                'type' => 'string',
                'group' => 'locale',
                'is_public' => true,
                'description' => 'Default currency code (ISO 4217)',
            ],
            [
                'key' => 'currency_symbol',
                'value' => '$',
                'type' => 'string',
                'group' => 'locale',
                'is_public' => true,
                'description' => 'Currency symbol',
            ],
            [
                'key' => 'timezone',
                'value' => 'America/Argentina/Buenos_Aires',
                'type' => 'string',
                'group' => 'locale',
                'is_public' => false,
                'description' => 'Store timezone',
            ],

            // Tax Settings (Private)
            [
                'key' => 'tax_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'tax',
                'is_public' => false,
                'description' => 'Enable tax calculation',
            ],
            [
                'key' => 'tax_rate',
                'value' => '21',
                'type' => 'float',
                'group' => 'tax',
                'is_public' => false,
                'description' => 'Default tax rate (IVA)',
            ],
            [
                'key' => 'tax_included_in_prices',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'tax',
                'is_public' => true,
                'description' => 'Prices include tax',
            ],

            // Shipping Settings (Private)
            [
                'key' => 'shipping_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'shipping',
                'is_public' => false,
                'description' => 'Enable shipping',
            ],
            [
                'key' => 'free_shipping_threshold',
                'value' => '50000',
                'type' => 'float',
                'group' => 'shipping',
                'is_public' => true,
                'description' => 'Order amount for free shipping',
            ],
            [
                'key' => 'shipping_origin_postal_code',
                'value' => null,
                'type' => 'string',
                'group' => 'shipping',
                'is_public' => false,
                'description' => 'Origin postal code for shipping calculation',
            ],

            // Payment Settings (Private)
            [
                'key' => 'mercadopago_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'payment',
                'is_public' => false,
                'description' => 'Enable Mercado Pago payments',
            ],
            [
                'key' => 'mercadopago_public_key',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'is_public' => false,
                'description' => 'Mercado Pago public key',
            ],
            [
                'key' => 'mercadopago_access_token',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'is_public' => false,
                'description' => 'Mercado Pago access token',
            ],

            // Notifications (Private)
            [
                'key' => 'notification_email_orders',
                'value' => null,
                'type' => 'string',
                'group' => 'notifications',
                'is_public' => false,
                'description' => 'Email for order notifications',
            ],
            [
                'key' => 'notification_low_stock',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notifications',
                'is_public' => false,
                'description' => 'Send low stock notifications',
            ],
            [
                'key' => 'low_stock_threshold',
                'value' => '5',
                'type' => 'integer',
                'group' => 'notifications',
                'is_public' => false,
                'description' => 'Low stock alert threshold',
            ],

            // Appearance (Public)
            [
                'key' => 'primary_color',
                'value' => '#3B82F6',
                'type' => 'string',
                'group' => 'appearance',
                'is_public' => true,
                'description' => 'Primary brand color',
            ],
            [
                'key' => 'secondary_color',
                'value' => '#1E40AF',
                'type' => 'string',
                'group' => 'appearance',
                'is_public' => true,
                'description' => 'Secondary brand color',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
