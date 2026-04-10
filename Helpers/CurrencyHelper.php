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
        $builtin = self::getBuiltinCurrencies();
        $raw = PluginSettings::getSetting('billingcore', 'currencies');
        if ($raw === null || $raw === '') {
            return $builtin;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $builtin;
        }

        $validByCode = [];
        foreach ($builtin as $currency) {
            $validByCode[$currency['code']] = $currency;
        }
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

            $validByCode[$code] = [
                'code' => $code,
                'name' => $name,
                'symbol' => $symbol,
            ];
        }

        return array_values($validByCode);
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
			['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
			['code' => 'AFN', 'name' => 'Afghan Afghani', 'symbol' => '؋'],
			['code' => 'ALL', 'name' => 'Albanian Lek', 'symbol' => 'L'],
			['code' => 'AMD', 'name' => 'Armenian Dram', 'symbol' => '֏'],
			['code' => 'ANG', 'name' => 'Netherlands Antillean Guilder', 'symbol' => 'ƒ'],
			['code' => 'AOA', 'name' => 'Angolan Kwanza', 'symbol' => 'Kz'],
			['code' => 'ARS', 'name' => 'Argentine Peso', 'symbol' => '$'],
			['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => '$'],
			['code' => 'AWG', 'name' => 'Aruban Florin', 'symbol' => 'ƒ'],
			['code' => 'AZN', 'name' => 'Azerbaijani Manat', 'symbol' => '₼'],
			['code' => 'BAM', 'name' => 'Bosnia-Herzegovina Mark', 'symbol' => 'KM'],
			['code' => 'BBD', 'name' => 'Barbadian Dollar', 'symbol' => '$'],
			['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳'],
			['code' => 'BGN', 'name' => 'Bulgarian Lev', 'symbol' => 'лв'],
			['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => '.د.ب'],
			['code' => 'BIF', 'name' => 'Burundian Franc', 'symbol' => 'FBu'],
			['code' => 'BMD', 'name' => 'Bermudan Dollar', 'symbol' => '$'],
			['code' => 'BND', 'name' => 'Brunei Dollar', 'symbol' => '$'],
			['code' => 'BOB', 'name' => 'Bolivian Boliviano', 'symbol' => '$b'],
			['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$'],
			['code' => 'BSD', 'name' => 'Bahamian Dollar', 'symbol' => '$'],
			['code' => 'BTN', 'name' => 'Bhutanese Ngultrum', 'symbol' => 'Nu.'],
			['code' => 'BWP', 'name' => 'Botswanan Pula', 'symbol' => 'P'],
			['code' => 'BYN', 'name' => 'Belarusian Ruble', 'symbol' => 'Br'],
			['code' => 'BZD', 'name' => 'Belize Dollar', 'symbol' => 'BZ$'],
			['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => '$'],
			['code' => 'CDF', 'name' => 'Congolese Franc', 'symbol' => 'FC'],
			['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
			['code' => 'CLP', 'name' => 'Chilean Peso', 'symbol' => '$'],
			['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
			['code' => 'COP', 'name' => 'Colombian Peso', 'symbol' => '$'],
			['code' => 'CRC', 'name' => 'Costa Rican Colón', 'symbol' => '₡'],
			['code' => 'CUP', 'name' => 'Cuban Peso', 'symbol' => '₱'],
			['code' => 'CVE', 'name' => 'Cape Verdean Escudo', 'symbol' => '$'],
			['code' => 'CZK', 'name' => 'Czech Koruna', 'symbol' => 'Kč'],
			['code' => 'DJF', 'name' => 'Djiboutian Franc', 'symbol' => 'Fdj'],
			['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr'],
			['code' => 'DOP', 'name' => 'Dominican Peso', 'symbol' => 'RD$'],
			['code' => 'DZD', 'name' => 'Algerian Dinar', 'symbol' => 'دج'],
			['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => '£'],
			['code' => 'ERN', 'name' => 'Eritrean Nakfa', 'symbol' => 'Nfk'],
			['code' => 'ETB', 'name' => 'Ethiopian Birr', 'symbol' => 'Br'],
			['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
			['code' => 'FJD', 'name' => 'Fijian Dollar', 'symbol' => '$'],
			['code' => 'FKP', 'name' => 'Falkland Islands Pound', 'symbol' => '£'],
			['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
			['code' => 'GEL', 'name' => 'Georgian Lari', 'symbol' => '₾'],
			['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => 'GH₵'],
			['code' => 'GIP', 'name' => 'Gibraltar Pound', 'symbol' => '£'],
			['code' => 'GMD', 'name' => 'Gambian Dalasi', 'symbol' => 'D'],
			['code' => 'GNF', 'name' => 'Guinean Franc', 'symbol' => 'FG'],
			['code' => 'GTQ', 'name' => 'Guatemalan Quetzal', 'symbol' => 'Q'],
			['code' => 'GYD', 'name' => 'Guyanese Dollar', 'symbol' => '$'],
			['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => '$'],
			['code' => 'HNL', 'name' => 'Honduran Lempira', 'symbol' => 'L'],
			['code' => 'HRK', 'name' => 'Croatian Kuna', 'symbol' => 'kn'],
			['code' => 'HTG', 'name' => 'Haitian Gourde', 'symbol' => 'G'],
			['code' => 'HUF', 'name' => 'Hungarian Forint', 'symbol' => 'Ft'],
			['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
			['code' => 'ILS', 'name' => 'Israeli New Shekel', 'symbol' => '₪'],
			['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
			['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'د.ع'],
			['code' => 'IRR', 'name' => 'Iranian Rial', 'symbol' => '﷼'],
			['code' => 'ISK', 'name' => 'Icelandic Króna', 'symbol' => 'kr'],
			['code' => 'JMD', 'name' => 'Jamaican Dollar', 'symbol' => 'J$'],
			['code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'JD'],
			['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
			['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
			['code' => 'KGS', 'name' => 'Kyrgystani Som', 'symbol' => 'лв'],
			['code' => 'KHR', 'name' => 'Cambodian Riel', 'symbol' => '៛'],
			['code' => 'KMF', 'name' => 'Comorian Franc', 'symbol' => 'CF'],
			['code' => 'KPW', 'name' => 'North Korean Won', 'symbol' => '₩'],
			['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩'],
			['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'KD'],
			['code' => 'KYD', 'name' => 'Cayman Islands Dollar', 'symbol' => '$'],
			['code' => 'KZT', 'name' => 'Kazakhstani Tenge', 'symbol' => '₸'],
			['code' => 'LAK', 'name' => 'Laotian Kip', 'symbol' => '₭'],
			['code' => 'LBP', 'name' => 'Lebanese Pound', 'symbol' => '£'],
			['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'symbol' => '₨'],
			['code' => 'LRD', 'name' => 'Liberian Dollar', 'symbol' => '$'],
			['code' => 'LSL', 'name' => 'Lesotho Loti', 'symbol' => 'L'],
			['code' => 'LYD', 'name' => 'Libyan Dinar', 'symbol' => 'LD'],
			['code' => 'MAD', 'name' => 'Moroccan Dirham', 'symbol' => 'MAD'],
			['code' => 'MDL', 'name' => 'Moldovan Leu', 'symbol' => 'L'],
			['code' => 'MGA', 'name' => 'Malagasy Ariary', 'symbol' => 'Ar'],
			['code' => 'MKD', 'name' => 'Macedonian Denar', 'symbol' => 'ден'],
			['code' => 'MMK', 'name' => 'Myanmar Kyat', 'symbol' => 'K'],
			['code' => 'MNT', 'name' => 'Mongolian Tugrik', 'symbol' => '₮'],
			['code' => 'MOP', 'name' => 'Macanese Pataca', 'symbol' => 'P'],
			['code' => 'MRU', 'name' => 'Mauritanian Ouguiya', 'symbol' => 'UM'],
			['code' => 'MUR', 'name' => 'Mauritian Rupee', 'symbol' => '₨'],
			['code' => 'MVR', 'name' => 'Maldivian Rufiyaa', 'symbol' => 'Rf'],
			['code' => 'MWK', 'name' => 'Malawian Kwacha', 'symbol' => 'MK'],
			['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => '$'],
			['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
			['code' => 'MZN', 'name' => 'Mozambican Metical', 'symbol' => 'MT'],
			['code' => 'NAD', 'name' => 'Namibian Dollar', 'symbol' => '$'],
			['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦'],
			['code' => 'NIO', 'name' => 'Nicaraguan Córdoba', 'symbol' => 'C$'],
			['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr'],
			['code' => 'NPR', 'name' => 'Nepalese Rupee', 'symbol' => '₨'],
			['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => '$'],
			['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => '﷼'],
			['code' => 'PAB', 'name' => 'Panamanian Balboa', 'symbol' => 'B/.'],
			['code' => 'PEN', 'name' => 'Peruvian Sol', 'symbol' => 'S/.'],
			['code' => 'PGK', 'name' => 'Papua New Guinean Kina', 'symbol' => 'K'],
			['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => '₱'],
			['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨'],
			['code' => 'PLN', 'name' => 'Polish Zloty', 'symbol' => 'zł'],
			['code' => 'PYG', 'name' => 'Paraguayan Guarani', 'symbol' => 'Gs'],
			['code' => 'QAR', 'name' => 'Qatari Rial', 'symbol' => '﷼'],
			['code' => 'RON', 'name' => 'Romanian Leu', 'symbol' => 'lei'],
			['code' => 'RSD', 'name' => 'Serbian Dinar', 'symbol' => 'Дин.'],
			['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽'],
			['code' => 'RWF', 'name' => 'Rwanda Franc', 'symbol' => 'R₣'],
			['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼'],
			['code' => 'SBD', 'name' => 'Solomon Islands Dollar', 'symbol' => '$'],
			['code' => 'SCR', 'name' => 'Seychellois Rupee', 'symbol' => '₨'],
			['code' => 'SDG', 'name' => 'Sudanese Pound', 'symbol' => '£'],
			['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr'],
			['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => '$'],
			['code' => 'SHP', 'name' => 'Saint Helena Pound', 'symbol' => '£'],
			['code' => 'SLL', 'name' => 'Sierra Leonean Leone', 'symbol' => 'Le'],
			['code' => 'SOS', 'name' => 'Somali Shilling', 'symbol' => 'S'],
			['code' => 'SRD', 'name' => 'Surinamese Dollar', 'symbol' => '$'],
			['code' => 'SSP', 'name' => 'South Sudanese Pound', 'symbol' => '£'],
			['code' => 'STN', 'name' => 'São Tomé and Príncipe Dobra', 'symbol' => 'Db'],
			['code' => 'SYP', 'name' => 'Syrian Pound', 'symbol' => '£'],
			['code' => 'SZL', 'name' => 'Swazi Lilangeni', 'symbol' => 'L'],
			['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
			['code' => 'TJS', 'name' => 'Tajikistani Somoni', 'symbol' => 'SM'],
			['code' => 'TMT', 'name' => 'Turkmenistani Manat', 'symbol' => 'T'],
			['code' => 'TND', 'name' => 'Tunisian Dinar', 'symbol' => 'د.ت'],
			['code' => 'TOP', 'name' => 'Tongan Paʻanga', 'symbol' => 'T$'],
			['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺'],
			['code' => 'TTD', 'name' => 'Trinidad and Tobago Dollar', 'symbol' => 'TT$'],
			['code' => 'TWD', 'name' => 'New Taiwan Dollar', 'symbol' => 'NT$'],
			['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'symbol' => 'TSh'],
			['code' => 'UAH', 'name' => 'Ukrainian Hryvnia', 'symbol' => '₴'],
			['code' => 'UGX', 'name' => 'Ugandan Shilling', 'symbol' => 'USh'],
			['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
			['code' => 'UYU', 'name' => 'Uruguayan Peso', 'symbol' => '$U'],
			['code' => 'UZS', 'name' => 'Uzbekistan Som', 'symbol' => 'лв'],
			['code' => 'VES', 'name' => 'Venezuelan Bolívar Soberano', 'symbol' => 'Bs.S'],
			['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫'],
			['code' => 'VUV', 'name' => 'Vanuatu Vatu', 'symbol' => 'VT'],
			['code' => 'WST', 'name' => 'Samoan Tala', 'symbol' => 'WS$'],
			['code' => 'XAF', 'name' => 'CFA Franc BEAC', 'symbol' => 'FCFA'],
			['code' => 'XCD', 'name' => 'East Caribbean Dollar', 'symbol' => '$'],
			['code' => 'XOF', 'name' => 'CFA Franc BCEAO', 'symbol' => 'CFA'],
			['code' => 'XPF', 'name' => 'CFP Franc', 'symbol' => '₣'],
			['code' => 'YER', 'name' => 'Yemeni Rial', 'symbol' => '﷼'],
			['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R'],
			['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'ZK'],
			['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG'],
		];
	}
}
