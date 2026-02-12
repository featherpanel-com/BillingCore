<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\billingcore\Helpers;

use App\Plugins\PluginSettings;

/**
 * Helper for managing billing currencies in the billingcore addon.
 *
 * Uses ISO currency codes and allows overriding via PluginSettings.
 *
 * PluginSettings (plugin identifier: "billingcore"):
 *  - key "currencies": JSON array of {code, name, symbol}
 *  - key "default_currency": ISO code string, e.g. "EUR"
 */
class CurrencyHelper
{
    /**
     * Default currency ISO code.
     */
    private const DEFAULT_CURRENCY_CODE = 'EUR';

    /**
     * Get all currencies configured for billingcore.
     *
     * If PluginSettings "currencies" is not set or invalid, falls back
     * to a small built‑in list with EUR / USD / etc.
     *
     * @return array<int, array{code:string,name:string,symbol:string}>
     */
    public static function getAvailableCurrencies(): array
    {
        $raw = PluginSettings::getSetting('billingcore', 'currencies');
        if ($raw === null || $raw === '') {
            return self::getBuiltinCurrencies();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::getBuiltinCurrencies();
        }

        $valid = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = isset($item['code']) && is_string($item['code']) ? strtoupper(trim($item['code'])) : null;
            $name = isset($item['name']) && is_string($item['name']) ? trim($item['name']) : null;
            $symbol = isset($item['symbol']) && is_string($item['symbol']) ? trim($item['symbol']) : null;

            if ($code === null || $name === null || $symbol === null) {
                continue;
            }

            // Basic ISO 4217 format check: 3 uppercase letters.
            if (!preg_match('/^[A-Z]{3}$/', $code)) {
                continue;
            }

            $valid[] = [
                'code' => $code,
                'name' => $name,
                'symbol' => $symbol,
            ];
        }

        if ($valid === []) {
            return self::getBuiltinCurrencies();
        }

        return $valid;
    }

    /**
     * List all currencies that the host can choose from.
     *
     * This is just a public alias for getAvailableCurrencies()
     * for clearer intent in addon code / UI.
     *
     * @return array<int, array{code:string,name:string,symbol:string}>
     */
    public static function listCurrencies(): array
    {
        return self::getAvailableCurrencies();
    }

    /**
     * Get the default currency (code, name, symbol).
     *
     * Respects PluginSettings "default_currency" if it matches a configured
     * currency, otherwise falls back to EUR.
     *
     * @return array{code:string,name:string,symbol:string}
     */
    public static function getDefaultCurrency(): array
    {
        $currencies = self::getAvailableCurrencies();
        $defaultCode = PluginSettings::getSetting('billingcore', 'default_currency');
        $defaultCode = is_string($defaultCode) ? strtoupper(trim($defaultCode)) : null;

        if ($defaultCode !== null) {
            foreach ($currencies as $currency) {
                if ($currency['code'] === $defaultCode) {
                    return $currency;
                }
            }
        }

        // Fallback to EUR or the first available currency.
        foreach ($currencies as $currency) {
            if ($currency['code'] === self::DEFAULT_CURRENCY_CODE) {
                return $currency;
            }
        }

        return $currencies[0];
    }

    /**
     * Get a currency by ISO code.
     *
     * @return array{code:string,name:string,symbol:string}|null
     */
    public static function getCurrencyByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        foreach (self::getAvailableCurrencies() as $currency) {
            if ($currency['code'] === $code) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Check if a currency code is valid for billingcore.
     */
    public static function isValidCurrencyCode(string $code): bool
    {
        return self::getCurrencyByCode($code) !== null;
    }

    /**
     * Get the credits mode setting.
     *
     * @return 'currency'|'token'
     */
    public static function getCreditsMode(): string
    {
        $mode = PluginSettings::getSetting('billingcore', 'credits_mode');
        if ($mode === null || $mode === '') {
            return 'currency'; // Default to currency mode
        }

        return in_array($mode, ['currency', 'token'], true) ? $mode : 'currency';
    }

    /**
     * Format an amount with the given or default currency.
     *
     * NOTE: The host only uses a single global currency. This helper
     * always uses the current default currency; no per‑user or per‑item
     * currencies are supported.
     *
     * This is purely cosmetic (no rounding logic beyond 2 decimals).
     */
    public static function formatAmount(float | int $amount): string
    {
        $creditsMode = self::getCreditsMode();

        if ($creditsMode === 'token') {
            // Token mode: show as "X Credits"
            $value = number_format((float) $amount, 2, '.', '');

            return $value . ' Credits';
        }

        // Currency mode: show with currency symbol
        $currency = self::getDefaultCurrency();
        $symbol = $currency['symbol'];
        $code = $currency['code'];
        $value = number_format((float) $amount, 2, '.', '');

        // Example output: "€ 10.00 (EUR)"
        return $symbol . ' ' . $value . ' (' . $code . ')';
    }

    /**
     * Built‑in default currencies (ISO 4217).
     *
     * NOTE: This is a small, sane default set. Admins can override/extend
     * using PluginSettings "currencies".
     *
     * @return array<int, array{code:string,name:string,symbol:string}>
     */
    private static function getBuiltinCurrencies(): array
    {
        return [
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
            ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr'],
            ['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr'],
            ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr'],
            ['code' => 'PLN', 'name' => 'Polish Złoty', 'symbol' => 'zł'],
        ];
    }
}
