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
 * InvoiceItem chat model for CRUD operations on the
 * featherpanel_billingcore_invoice_items table.
 */
class InvoiceItem
{
    private static string $table = 'featherpanel_billingcore_invoice_items';

    /**
     * Get invoice item by ID.
     */
    public static function getById(int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all items for an invoice.
     *
     * @return array<int,array>
     */
    public static function getByInvoiceId(int $invoiceId): array
    {
        if ($invoiceId <= 0) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE invoice_id = :invoice_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create a new invoice item.
     *
     * @param array<string,mixed> $data
     */
    public static function create(array $data): ?int
    {
        $allowedFields = [
            'invoice_id',
            'description',
            'quantity',
            'unit_price',
            'total',
            'sort_order',
        ];

        $payload = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        // Required fields
        if (!isset($payload['invoice_id']) || !isset($payload['description'])) {
            return null;
        }

        // Defaults
        $payload['quantity'] = $payload['quantity'] ?? 1.00;
        $payload['unit_price'] = $payload['unit_price'] ?? 0.00;
        $payload['sort_order'] = $payload['sort_order'] ?? 0;

        // Calculate total if not provided
        if (!isset($payload['total'])) {
            $payload['total'] = (float) $payload['quantity'] * (float) $payload['unit_price'];
        }

        $pdo = Database::getPdoConnection();

        try {
            $fields = array_keys($payload);
            $placeholders = array_map(static fn (string $f): string => ':' . $f, $fields);
            $sql = 'INSERT INTO ' . self::$table . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($payload);

            return (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to create invoice item: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update an invoice item.
     *
     * @param array<string,mixed> $data
     */
    public static function update(int $itemId, array $data): bool
    {
        if ($itemId <= 0) {
            return false;
        }

        $allowedFields = [
            'description',
            'quantity',
            'unit_price',
            'total',
            'sort_order',
        ];

        $payload = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($payload === []) {
            return false;
        }

        // Recalculate total if quantity or unit_price changed
        if (isset($payload['quantity']) || isset($payload['unit_price'])) {
            $existing = self::getById($itemId);
            if ($existing) {
                $quantity = $payload['quantity'] ?? $existing['quantity'];
                $unitPrice = $payload['unit_price'] ?? $existing['unit_price'];
                $payload['total'] = (float) $quantity * (float) $unitPrice;
            }
        }

        $pdo = Database::getPdoConnection();

        try {
            $fields = array_keys($payload);
            $setClause = implode(', ', array_map(static fn (string $f): string => $f . ' = :' . $f, $fields));
            $sql = 'UPDATE ' . self::$table . ' SET ' . $setClause . ' WHERE id = :id';
            $payload['id'] = $itemId;
            $stmt = $pdo->prepare($sql);

            return $stmt->execute($payload);
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to update invoice item: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete an invoice item.
     */
    public static function delete(int $itemId): bool
    {
        if ($itemId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');
            $stmt->execute(['id' => $itemId]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete invoice item: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete all items for an invoice.
     */
    public static function deleteByInvoiceId(int $invoiceId): bool
    {
        if ($invoiceId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE invoice_id = :invoice_id');
            $stmt->execute(['invoice_id' => $invoiceId]);

            return true;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete invoice items: ' . $e->getMessage());

            return false;
        }
    }
}
