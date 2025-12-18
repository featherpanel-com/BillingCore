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
use App\Chat\User;
use App\Chat\Database;

/**
 * Billing chat for CRUD and credit operations
 * on the featherpanel_billing table, scoped to the billingcore addon.
 */
class Billing
{
    /**
     * @var string the billing table name
     */
    private static string $table = 'featherpanel_billing';

    /**
     * Get a billing row by user ID.
     */
    public static function getByUserId(int $userId): ?array
    {
        if (!self::assertUserExists($userId)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ensure a billing row exists for the given user.
     *
     * @return array|null the billing row or null on failure
     */
    public static function getOrCreateByUserId(int $userId): ?array
    {
        if (!self::assertUserExists($userId)) {
            return null;
        }

        $existing = self::getByUserId($userId);
        if ($existing !== null) {
            return $existing;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (user_id, credits) VALUES (:user_id, 0)'
            );
            $stmt->execute(['user_id' => $userId]);
        } catch (\PDOException $e) {
            // Duplicate key means another request created the row – fetch it.
            if ($e->getCode() !== '23000') {
                App::getInstance(true)->getLogger()->error('Failed to create billing row: ' . $e->getMessage());

                return null;
            }
        }

        return self::getByUserId($userId);
    }

    /**
     * Get the current credits for a user.
     *
     * If the user exists but has no billing row yet, a row will be created with 0 credits.
     */
    public static function getCredits(int $userId): int
    {
        $row = self::getOrCreateByUserId($userId);

        return $row !== null ? (int) ($row['credits'] ?? 0) : 0;
    }

    /**
     * Set the exact credits value for a user.
     *
     * This is wrapped in a transaction and uses row locking
     * to be safe under concurrent requests.
     */
    public static function setCredits(int $userId, int $credits): bool
    {
        if ($credits < 0) {
            return false;
        }

        if (!self::assertUserExists($userId)) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            $row = self::lockRowForUser($pdo, $userId);
            if ($row === null) {
                // Create row if it does not exist.
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO ' . self::$table . ' (user_id, credits) VALUES (:user_id, :credits)'
                );
                $stmtInsert->execute([
                    'user_id' => $userId,
                    'credits' => $credits,
                ]);
            } else {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE ' . self::$table . ' SET credits = :credits WHERE id = :id'
                );
                $stmtUpdate->execute([
                    'credits' => $credits,
                    'id' => (int) $row['id'],
                ]);
            }

            $pdo->commit();

            return true;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            App::getInstance(true)->getLogger()->error('Failed to set credits: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Add credits to a user's balance.
     *
     * @return bool true on success, false on failure
     */
    public static function addCredits(int $userId, int $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        return self::adjustCredits($userId, $amount);
    }

    /**
     * Remove credits from a user's balance.
     *
     * This will never allow the balance to go below zero.
     *
     * @return bool true on success, false if insufficient credits or on error
     */
    public static function removeCredits(int $userId, int $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        return self::adjustCredits($userId, -$amount);
    }

    /**
     * Atomically adjust credits by a signed delta.
     *
     * Uses a transaction and SELECT ... FOR UPDATE to be race-safe.
     */
    public static function adjustCredits(int $userId, int $delta): bool
    {
        if (!self::assertUserExists($userId)) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            $row = self::lockRowForUser($pdo, $userId);
            if ($row === null) {
                // Create a new row starting at 0 credits.
                $currentCredits = 0;
            } else {
                $currentCredits = (int) ($row['credits'] ?? 0);
            }

            $newCredits = $currentCredits + $delta;
            if ($newCredits < 0) {
                // Not enough credits – rollback and fail.
                $pdo->rollBack();

                return false;
            }

            if ($row === null) {
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO ' . self::$table . ' (user_id, credits) VALUES (:user_id, :credits)'
                );
                $stmtInsert->execute([
                    'user_id' => $userId,
                    'credits' => $newCredits,
                ]);
            } else {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE ' . self::$table . ' SET credits = :credits WHERE id = :id'
                );
                $stmtUpdate->execute([
                    'credits' => $newCredits,
                    'id' => (int) $row['id'],
                ]);
            }

            $pdo->commit();

            return true;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            App::getInstance(true)->getLogger()->error('Failed to adjust credits: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Ensure the user exists in featherpanel_users.
     */
    private static function assertUserExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = User::getUserById($userId);

        return $user !== null;
    }

    /**
     * Lock a billing row for a given user using SELECT ... FOR UPDATE.
     *
     * @param \PDO $pdo active PDO connection with an open transaction
     *
     * @return array|null the locked row or null if none exists
     */
    private static function lockRowForUser(\PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
