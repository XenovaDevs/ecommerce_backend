<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cart = Cart::factory()->create(['user_id' => $this->user->id]);
});

test('calculates tax when tax is enabled and not included in prices', function () {
    Setting::create(['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_included_in_prices', 'value' => '0', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_rate', 'value' => '21', 'type' => 'float']);

    $product = Product::factory()->create(['price' => 100.00]);

    CartItem::factory()->create([
        'cart_id' => $this->cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price_at_addition' => 100.00,
    ]);

    $this->cart->load('items.product', 'items.variant');

    expect($this->cart->subtotal)->toBe(200.0)
        ->and($this->cart->tax)->toBe(42.0)
        ->and($this->cart->total)->toBe(242.0);
});

test('returns zero tax when tax is disabled', function () {
    Setting::create(['key' => 'tax_enabled', 'value' => '0', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_included_in_prices', 'value' => '0', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_rate', 'value' => '21', 'type' => 'float']);

    $product = Product::factory()->create(['price' => 100.00]);

    CartItem::factory()->create([
        'cart_id' => $this->cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price_at_addition' => 100.00,
    ]);

    $this->cart->load('items.product', 'items.variant');

    expect($this->cart->subtotal)->toBe(200.0)
        ->and($this->cart->tax)->toBe(0.0)
        ->and($this->cart->total)->toBe(200.0);
});

test('returns zero tax when tax is included in prices', function () {
    Setting::create(['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_included_in_prices', 'value' => '1', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_rate', 'value' => '21', 'type' => 'float']);

    $product = Product::factory()->create(['price' => 100.00]);

    CartItem::factory()->create([
        'cart_id' => $this->cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price_at_addition' => 100.00,
    ]);

    $this->cart->load('items.product', 'items.variant');

    expect($this->cart->subtotal)->toBe(200.0)
        ->and($this->cart->tax)->toBe(0.0)
        ->and($this->cart->total)->toBe(200.0);
});

test('calculates tax with custom tax rate', function () {
    Setting::create(['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_included_in_prices', 'value' => '0', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_rate', 'value' => '10', 'type' => 'float']);

    $product = Product::factory()->create(['price' => 100.00]);

    CartItem::factory()->create([
        'cart_id' => $this->cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price_at_addition' => 100.00,
    ]);

    $this->cart->load('items.product', 'items.variant');

    expect($this->cart->subtotal)->toBe(100.0)
        ->and($this->cart->tax)->toBe(10.0)
        ->and($this->cart->total)->toBe(110.0);
});

test('rounds tax to two decimal places', function () {
    Setting::create(['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_included_in_prices', 'value' => '0', 'type' => 'boolean']);
    Setting::create(['key' => 'tax_rate', 'value' => '21', 'type' => 'float']);

    $product = Product::factory()->create(['price' => 33.33]);

    CartItem::factory()->create([
        'cart_id' => $this->cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price_at_addition' => 33.33,
    ]);

    $this->cart->load('items.product', 'items.variant');

    expect($this->cart->subtotal)->toBe(33.33)
        ->and($this->cart->tax)->toBe(7.0); // 33.33 * 0.21 = 6.9993 -> 7.00
});
