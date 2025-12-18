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

namespace App\Addons\billingcore\Chat;

use App\App;
use App\Chat\Database;

/**
 * UserBillingInfo chat model for CRUD operations on the
 * featherpanel_billing_user_info table, scoped to the billingcore addon.
 */
class UserBillingInfo
{
    /**
     * @var string the user billing info table name
     */
    private static string $table = 'featherpanel_billing_user_info';

    /**
     * Get billing info by user ID.
     */
    public static function getByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create or update billing info for a user.
     *
     * @param int $userId user id
     * @param array $data associative array of billing fields
     */
    public static function createOrUpdate(int $userId, array $data): bool
    {
        if ($userId <= 0) {
            return false;
        }

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

        $payload = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }
                $payload[$field] = $value;
            }
        }

        if ($payload === []) {
            return false;
        }

        // Basic required fields validation when creating
        $existing = self::getByUserId($userId);
        if ($existing === null) {
            $required = ['full_name', 'address_line1', 'city', 'postal_code', 'country_code'];
            foreach ($required as $field) {
                if (!isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === null) {
                    return false;
                }
            }
        }

        // Normalize country code
        if (isset($payload['country_code']) && is_string($payload['country_code'])) {
            $payload['country_code'] = strtoupper(trim($payload['country_code']));
        }

        $pdo = Database::getPdoConnection();

        try {
            if ($existing === null) {
                // Insert
                $payload['user_id'] = $userId;
                $fields = array_keys($payload);
                $placeholders = array_map(static fn (string $f): string => ':' . $f, $fields);
                $sql = 'INSERT INTO ' . self::$table . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);

                return $stmt->execute($payload);
            }

            // Update
            $fields = array_keys($payload);
            $setClause = implode(', ', array_map(static fn (string $f): string => $f . ' = :' . $f, $fields));
            $sql = 'UPDATE ' . self::$table . ' SET ' . $setClause . ' WHERE user_id = :user_id';
            $payload['user_id'] = $userId;
            $stmt = $pdo->prepare($sql);

            return $stmt->execute($payload);
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to create or update billing info: ' . $e->getMessage());

            return false;
        }
    }
}
