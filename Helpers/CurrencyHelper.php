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
