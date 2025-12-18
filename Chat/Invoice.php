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
 * Invoice chat model for CRUD operations on the
 * featherpanel_billingcore_invoices table.
 */
class Invoice
{
    private static string $table = 'featherpanel_billingcore_invoices';

    public static function getTableName(): string
    {
        return self::$table;
    }

    /**
     * Get invoice by ID.
     */
    public static function getById(int $invoiceId): ?array
    {
        if ($invoiceId <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $invoiceId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get invoice by invoice number.
     */
    public static function getByInvoiceNumber(string $invoiceNumber): ?array
    {
        if (empty($invoiceNumber)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE invoice_number = :invoice_number LIMIT 1');
        $stmt->execute(['invoice_number' => $invoiceNumber]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all invoices for a user.
     *
     * @return array<int,array>
     */
    public static function getByUserId(int $userId, ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $sql = 'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($status !== null && in_array($status, ['draft', 'pending', 'paid', 'overdue', 'cancelled'], true)) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        if (isset($params['status'])) {
            $stmt->bindValue(':status', $params['status'], \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Count invoices for a user.
     */
    public static function countByUserId(int $userId, ?string $status = null): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $pdo = Database::getPdoConnection();
        $sql = 'SELECT COUNT(*) as count FROM ' . self::$table . ' WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($status !== null && in_array($status, ['draft', 'pending', 'paid', 'overdue', 'cancelled'], true)) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Create a new invoice.
     *
     * @param array<string,mixed> $data
     */
    public static function create(array $data): ?int
    {
        $allowedFields = [
            'user_id',
            'invoice_number',
            'status',
            'due_date',
            'subtotal',
            'tax_rate',
            'tax_amount',
            'total',
            'currency_code',
            'notes',
        ];

        $payload = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        // Required fields
        if (!isset($payload['user_id']) || !isset($payload['invoice_number'])) {
            return null;
        }

        // Defaults
        $payload['status'] = $payload['status'] ?? 'draft';
        $payload['subtotal'] = $payload['subtotal'] ?? 0.00;
        $payload['tax_rate'] = $payload['tax_rate'] ?? 0.00;
        $payload['tax_amount'] = $payload['tax_amount'] ?? 0.00;
        $payload['total'] = $payload['total'] ?? 0.00;
        $payload['currency_code'] = $payload['currency_code'] ?? 'EUR';

        $pdo = Database::getPdoConnection();

        try {
            $fields = array_keys($payload);
            $placeholders = array_map(static fn (string $f): string => ':' . $f, $fields);
            $sql = 'INSERT INTO ' . self::$table . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($payload);

            return (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to create invoice: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update an invoice.
     *
     * @param array<string,mixed> $data
     */
    public static function update(int $invoiceId, array $data): bool
    {
        if ($invoiceId <= 0) {
            return false;
        }

        $allowedFields = [
            'status',
            'due_date',
            'paid_at',
            'subtotal',
            'tax_rate',
            'tax_amount',
            'total',
            'currency_code',
            'notes',
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

        $pdo = Database::getPdoConnection();

        try {
            $fields = array_keys($payload);
            $setClause = implode(', ', array_map(static fn (string $f): string => $f . ' = :' . $f, $fields));
            $sql = 'UPDATE ' . self::$table . ' SET ' . $setClause . ' WHERE id = :id';
            $payload['id'] = $invoiceId;
            $stmt = $pdo->prepare($sql);

            return $stmt->execute($payload);
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to update invoice: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete an invoice (cascade deletes items).
     */
    public static function delete(int $invoiceId): bool
    {
        if ($invoiceId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');
            $stmt->execute(['id' => $invoiceId]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete invoice: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-';
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));

        return $prefix . $date . '-' . $random;
    }
}
