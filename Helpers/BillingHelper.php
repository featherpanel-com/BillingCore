<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Addons\billingcore\Helpers;

use App\App;
use App\Plugins\PluginSettings;
use App\Addons\billingcore\Chat\Invoice;
use App\Addons\billingcore\Chat\InvoiceItem;
use App\Addons\billingcore\Chat\UserBillingInfo;

/**
 * BillingHelper provides high-level helpers for working with
 * user and admin billing information in the billingcore addon.
 */
class BillingHelper
{
    /**
     * Check if a user already has a billing info record.
     */
    public static function userHasBillingInfo(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return UserBillingInfo::getByUserId($userId) !== null;
    }

    /**
     * Get the raw billing info row for a user (or null if none).
     */
    public static function getUserBillingInfo(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return UserBillingInfo::getByUserId($userId);
    }

    /**
     * Get user billing info, always returning a complete structure.
     *
     * If the user has no billing info yet, this returns a default
     * array with all expected keys set to null. It does NOT create
     * a row in the database; it is purely a read helper.
     *
     * @return array<string,mixed>
     */
    public static function getUserBillingInfoOrDefault(int $userId): array
    {
        if ($userId <= 0) {
            return self::defaultBillingInfoStructure(null);
        }

        $info = UserBillingInfo::getByUserId($userId);

        if ($info === null) {
            $info = self::defaultBillingInfoStructure($userId);
        }

        return $info;
    }

    /**
     * Ensure a user has a billing info record by creating one if missing.
     *
     * This will create a row with the provided $defaults merged into the
     * required default structure. If creation fails (e.g. because required
     * fields are missing), it logs an error and returns null.
     *
     * @param array<string,mixed> $defaults
     *
     * @return array<string,mixed>|null The existing or newly created billing info
     */
    public static function ensureUserBillingInfo(int $userId, array $defaults = []): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $existing = UserBillingInfo::getByUserId($userId);
        if ($existing !== null) {
            return $existing;
        }

        $base = self::defaultBillingInfoStructure($userId);
        $payload = array_merge($base, $defaults);

        if (!UserBillingInfo::createOrUpdate($userId, $payload)) {
            App::getInstance(true)->getLogger()->error(
                'BillingHelper::ensureUserBillingInfo failed to create billing info'
            );

            return null;
        }

        return UserBillingInfo::getByUserId($userId);
    }

    /**
     * Get the admin billing info from PluginSettings.
     *
     * @return array<string,mixed> Billing info structure (all keys present)
     */
    public static function getAdminBillingInfo(): array
    {
        $billingInfoJson = PluginSettings::getSetting('billingcore', 'admin_billing_info');
        $billingInfo = null;

        if ($billingInfoJson !== null && $billingInfoJson !== '') {
            $decoded = html_entity_decode($billingInfoJson, ENT_QUOTES, 'UTF-8');
            $parsed = json_decode($decoded, true);
            if (json_last_error() === 0 && is_array($parsed)) {
                $billingInfo = $parsed;
            }
        }

        if (!is_array($billingInfo)) {
            $billingInfo = self::defaultBillingInfoStructure(null);
        } else {
            // Ensure all expected keys exist
            $billingInfo = array_merge(self::defaultBillingInfoStructure(null), $billingInfo);
        }

        return $billingInfo;
    }

    /**
     * Save admin billing info into PluginSettings.
     *
     * @param array<string,mixed> $data
     */
    public static function saveAdminBillingInfo(array $data): bool
    {
        // Whitelist allowed fields
        $allowedFields = [
            'full_name',
            'company_name',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country_code',
            'vat_id',
            'phone',
        ];

        $billingInfo = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }
                $billingInfo[$field] = $value ?: null;
            }
        }

        // Normalize country code
        if (isset($billingInfo['country_code']) && is_string($billingInfo['country_code'])) {
            $billingInfo['country_code'] = strtoupper(trim($billingInfo['country_code']));
        }

        try {
            PluginSettings::setSetting('billingcore', 'admin_billing_info', json_encode($billingInfo));

            return true;
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error(
                'BillingHelper::saveAdminBillingInfo failed: ' . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Get invoice by ID (with items).
     *
     * @return array<string,mixed>|null
     */
    public static function getInvoiceWithItems(int $invoiceId): ?array
    {
        if ($invoiceId <= 0) {
            return null;
        }

        $invoice = Invoice::getById($invoiceId);
        if ($invoice === null) {
            return null;
        }

        $items = InvoiceItem::getByInvoiceId($invoiceId);
        $invoice['items'] = $items;

        return $invoice;
    }

    /**
     * Create invoice with items and recalculate totals.
     *
     * @param array<string,mixed> $invoiceData
     * @param array<int,array<string,mixed>> $itemsData
     *
     * @return array<string,mixed>|null
     */
    public static function createInvoiceWithItems(int $userId, array $invoiceData, array $itemsData = []): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        // Generate invoice number if not provided
        if (!isset($invoiceData['invoice_number']) || empty($invoiceData['invoice_number'])) {
            $invoiceData['invoice_number'] = Invoice::generateInvoiceNumber();
        }

        // Ensure invoice number is unique
        while (Invoice::getByInvoiceNumber($invoiceData['invoice_number']) !== null) {
            $invoiceData['invoice_number'] = Invoice::generateInvoiceNumber();
        }

        $invoiceData['user_id'] = $userId;

        // Calculate totals from items if not provided
        if (!isset($invoiceData['subtotal']) && !empty($itemsData)) {
            $subtotal = 0.00;
            foreach ($itemsData as $item) {
                $quantity = (float) ($item['quantity'] ?? 1.00);
                $unitPrice = (float) ($item['unit_price'] ?? 0.00);
                $subtotal += $quantity * $unitPrice;
            }
            $invoiceData['subtotal'] = $subtotal;
        }

        $subtotal = (float) ($invoiceData['subtotal'] ?? 0.00);
        $taxRate = (float) ($invoiceData['tax_rate'] ?? 0.00);
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $invoiceData['tax_amount'] = $taxAmount;
        $invoiceData['total'] = $total;

        $invoiceId = Invoice::create($invoiceData);
        if ($invoiceId === null) {
            return null;
        }

        // Add items
        foreach ($itemsData as $index => $item) {
            $item['invoice_id'] = $invoiceId;
            $item['sort_order'] = $item['sort_order'] ?? $index;
            InvoiceItem::create($item);
        }

        return self::getInvoiceWithItems($invoiceId);
    }

    /**
     * Update invoice totals based on items.
     */
    public static function recalculateInvoiceTotals(int $invoiceId, float $taxRate = 0.00): bool
    {
        if ($invoiceId <= 0) {
            return false;
        }

        $items = InvoiceItem::getByInvoiceId($invoiceId);
        $subtotal = 0.00;

        foreach ($items as $item) {
            $subtotal += (float) $item['total'];
        }

        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        return Invoice::update($invoiceId, [
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    /**
     * Check if user has billing info before creating invoice.
     */
    public static function canCreateInvoice(int $userId): bool
    {
        return self::userHasBillingInfo($userId);
    }

    /**
     * Build a default billing info structure.
     *
     * @return array<string,mixed>
     */
    private static function defaultBillingInfoStructure(?int $userId): array
    {
        return [
            'user_id' => $userId,
            'full_name' => null,
            'company_name' => null,
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country_code' => null,
            'vat_id' => null,
            'phone' => null,
        ];
    }
}
