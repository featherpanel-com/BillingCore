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

namespace App\Addons\billingcore\Controllers\Admin;

use App\Chat\User;
use App\Chat\Activity;
use App\Chat\Database;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Plugins\PluginSettings;
use App\CloudFlare\CloudFlareRealIP;
use App\Addons\billingcore\Chat\Billing;
use App\Addons\billingcore\Chat\Invoice;
use App\Addons\billingcore\Chat\InvoiceItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Chat\UserBillingInfo;
use App\Addons\billingcore\Helpers\BillingHelper;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingcore\Helpers\CurrencyHelper;

#[OA\Tag(name: 'Admin - Billing Core', description: 'Billing and credits management for administrators')]
class BillingCoreController
{
    #[OA\Get(
        path: '/api/admin/billingcore/users',
        summary: 'Get all users with credits',
        description: 'Get paginated list of all users with their credit balances',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search by username, email, or UUID'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getUsers(Request $request): Response
    {
        $page = max((int) $request->query->get('page', 1), 1);
        $limit = min(max((int) $request->query->get('limit', 20), 1), 100);
        $offset = ($page - 1) * $limit;
        $search = $request->query->get('search', '');

        try {
            $pdo = Database::getPdoConnection();
            $where = [];
            $params = [];

            if (!empty($search)) {
                $where[] = '(u.username LIKE :search OR u.email LIKE :search OR u.uuid LIKE :search)';
                $params['search'] = '%' . $search . '%';
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // Get total count
            $countSql = 'SELECT COUNT(*) as count FROM featherpanel_users u ' . $whereClause;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch(\PDO::FETCH_ASSOC)['count'];

            // Get users with credits
            $sql = 'SELECT u.id, u.username, u.email, u.uuid, u.first_seen,
                    COALESCE(b.credits, 0) as credits
                    FROM featherpanel_users u
                    LEFT JOIN featherpanel_billing b ON u.id = b.user_id
                    ' . $whereClause . '
                    ORDER BY u.username ASC
                    LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Format credits with currency or token format
            $currency = CurrencyHelper::getDefaultCurrency();
            $creditsMode = CurrencyHelper::getCreditsMode();
            foreach ($users as &$user) {
                $user['credits'] = (int) ($user['credits'] ?? 0);
                $user['credits_formatted'] = CurrencyHelper::formatAmount($user['credits']);
            }
            unset($user);

            return ApiResponse::success([
                'data' => $users,
                'meta' => [
                    'pagination' => [
                        'total' => $total,
                        'count' => count($users),
                        'per_page' => $limit,
                        'current_page' => $page,
                        'total_pages' => (int) ceil($total / $limit),
                    ],
                    'currency' => $currency,
                    'credits_mode' => $creditsMode,
                ],
            ], 'Users retrieved successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve users: ' . $e->getMessage(), 'GET_USERS_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/billingcore/users/{userId}/credits',
        summary: 'Get user credits',
        description: 'Get credit balance for a specific user',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User credits retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function getUserCredits(Request $request, int $userId): Response
    {
        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $credits = CreditsHelper::getUserCredits($userId);
        $currency = CurrencyHelper::getDefaultCurrency();
        $creditsMode = CurrencyHelper::getCreditsMode();

        return ApiResponse::success([
            'user_id' => $userId,
            'username' => $user['username'],
            'email' => $user['email'],
            'uuid' => $user['uuid'],
            'credits' => $credits,
            'credits_formatted' => CurrencyHelper::formatAmount($credits),
            'currency' => $currency,
            'credits_mode' => $creditsMode,
        ], 'User credits retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingcore/users/{userId}/credits/add',
        summary: 'Add credits to user',
        description: 'Add credits to a user\'s balance',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'integer', description: 'Amount of credits to add', minimum: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Credits added successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function addUserCredits(Request $request, int $userId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $amount = isset($data['amount']) ? (int) $data['amount'] : null;
        if ($amount === null || $amount <= 0) {
            return ApiResponse::error('Invalid amount. Must be a positive integer', 'INVALID_AMOUNT', 400);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        if (!CreditsHelper::addUserCredits($userId, $amount)) {
            return ApiResponse::error('Failed to add credits', 'ADD_CREDITS_FAILED', 500);
        }

        $newBalance = CreditsHelper::getUserCredits($userId);
        $currency = CurrencyHelper::getDefaultCurrency();

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_add_credits',
            'context' => "Added {$amount} credits to user: {$user['username']} (ID: {$userId}). New balance: {$newBalance}",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'user_id' => $userId,
            'amount_added' => $amount,
            'new_balance' => $newBalance,
            'new_balance_formatted' => CurrencyHelper::formatAmount($newBalance),
            'currency' => $currency,
        ], 'Credits added successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingcore/users/{userId}/credits/remove',
        summary: 'Remove credits from user',
        description: 'Remove credits from a user\'s balance (will not go below zero)',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'integer', description: 'Amount of credits to remove', minimum: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Credits removed successfully'),
            new OA\Response(response: 400, description: 'Invalid input or insufficient credits'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function removeUserCredits(Request $request, int $userId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $amount = isset($data['amount']) ? (int) $data['amount'] : null;
        if ($amount === null || $amount <= 0) {
            return ApiResponse::error('Invalid amount. Must be a positive integer', 'INVALID_AMOUNT', 400);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $oldBalance = CreditsHelper::getUserCredits($userId);
        if ($oldBalance < $amount) {
            return ApiResponse::error('Insufficient credits. User has ' . $oldBalance . ' credits', 'INSUFFICIENT_CREDITS', 400);
        }

        if (!CreditsHelper::removeUserCredits($userId, $amount)) {
            return ApiResponse::error('Failed to remove credits', 'REMOVE_CREDITS_FAILED', 500);
        }

        $newBalance = CreditsHelper::getUserCredits($userId);
        $currency = CurrencyHelper::getDefaultCurrency();

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_remove_credits',
            'context' => "Removed {$amount} credits from user: {$user['username']} (ID: {$userId}). Old balance: {$oldBalance}, New balance: {$newBalance}",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'user_id' => $userId,
            'amount_removed' => $amount,
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
            'new_balance_formatted' => CurrencyHelper::formatAmount($newBalance),
            'currency' => $currency,
        ], 'Credits removed successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingcore/users/{userId}/credits/set',
        summary: 'Set user credits',
        description: 'Set a user\'s credit balance to a specific amount',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'integer', description: 'New credit balance', minimum: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Credits set successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function setUserCredits(Request $request, int $userId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $amount = isset($data['amount']) ? (int) $data['amount'] : null;
        if ($amount === null || $amount < 0) {
            return ApiResponse::error('Invalid amount. Must be a non-negative integer', 'INVALID_AMOUNT', 400);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $oldBalance = CreditsHelper::getUserCredits($userId);
        if (!Billing::setCredits($userId, $amount)) {
            return ApiResponse::error('Failed to set credits', 'SET_CREDITS_FAILED', 500);
        }

        $currency = CurrencyHelper::getDefaultCurrency();

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_set_credits',
            'context' => "Set credits for user: {$user['username']} (ID: {$userId}). Old balance: {$oldBalance}, New balance: {$amount}",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'user_id' => $userId,
            'old_balance' => $oldBalance,
            'new_balance' => $amount,
            'new_balance_formatted' => CurrencyHelper::formatAmount($amount),
            'currency' => $currency,
        ], 'Credits set successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingcore/users/search',
        summary: 'Search users',
        description: 'Search users by username, email, or UUID',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'query', in: 'query', required: true, schema: new OA\Schema(type: 'string'), description: 'Search query'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users retrieved successfully'),
            new OA\Response(response: 400, description: 'Invalid query'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function searchUsers(Request $request): Response
    {
        $query = $request->query->get('query', '');
        $limit = min(max((int) $request->query->get('limit', 20), 1), 100);

        if (empty($query) || strlen($query) < 2) {
            return ApiResponse::error('Query must be at least 2 characters', 'INVALID_QUERY', 400);
        }

        try {
            $pdo = Database::getPdoConnection();
            $searchPattern = '%' . $query . '%';
            $sql = 'SELECT u.id, u.username, u.email, u.uuid,
                    COALESCE(b.credits, 0) as credits
                    FROM featherpanel_users u
                    LEFT JOIN featherpanel_billing b ON u.id = b.user_id
                    WHERE u.username LIKE :query
                       OR u.email LIKE :query
                       OR u.uuid LIKE :query
                    ORDER BY u.username ASC
                    LIMIT ' . $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['query' => $searchPattern]);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Format credits with currency
            $currency = CurrencyHelper::getDefaultCurrency();
            foreach ($users as &$user) {
                $user['credits'] = (int) ($user['credits'] ?? 0);
                $user['credits_formatted'] = CurrencyHelper::formatAmount($user['credits']);
            }
            unset($user);

            return ApiResponse::success([
                'data' => $users,
                'count' => count($users),
                'currency' => $currency,
            ], 'Users retrieved successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to search users: ' . $e->getMessage(), 'SEARCH_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/billingcore/currency/settings',
        summary: 'Get currency settings',
        description: 'Get current currency configuration',
        tags: ['Admin - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Currency settings retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getCurrencySettings(Request $request): Response
    {
        $defaultCurrency = CurrencyHelper::getDefaultCurrency();
        $availableCurrencies = CurrencyHelper::listCurrencies();

        return ApiResponse::success([
            'default_currency' => $defaultCurrency,
            'available_currencies' => $availableCurrencies,
        ], 'Currency settings retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingcore/currency/settings',
        summary: 'Update currency settings',
        description: 'Update the default currency',
        tags: ['Admin - Billing Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['default_currency'],
                properties: [
                    new OA\Property(property: 'default_currency', type: 'string', description: 'ISO currency code (e.g., EUR, USD)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Currency settings updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function updateCurrencySettings(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $currencyCode = $data['default_currency'] ?? null;
        if (!$currencyCode || !is_string($currencyCode)) {
            return ApiResponse::error('Missing or invalid default_currency', 'INVALID_CURRENCY', 400);
        }

        // Validate currency code
        if (!CurrencyHelper::isValidCurrencyCode($currencyCode)) {
            return ApiResponse::error('Invalid currency code. Use a valid ISO currency code', 'INVALID_CURRENCY_CODE', 400);
        }

        // Update setting
        PluginSettings::setSetting('billingcore', 'default_currency', strtoupper($currencyCode));

        $newCurrency = CurrencyHelper::getDefaultCurrency();

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_update_currency',
            'context' => "Updated default currency to: {$newCurrency['code']} ({$newCurrency['name']})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'default_currency' => $newCurrency,
        ], 'Currency settings updated successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingcore/currency/list',
        summary: 'List available currencies',
        description: 'Get list of all available currency options',
        tags: ['Admin - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Currencies retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function listCurrencies(Request $request): Response
    {
        $currencies = CurrencyHelper::listCurrencies();
        $defaultCurrency = CurrencyHelper::getDefaultCurrency();

        return ApiResponse::success([
            'currencies' => $currencies,
            'default_currency' => $defaultCurrency,
        ], 'Currencies retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingcore/statistics',
        summary: 'Get statistics',
        description: 'Get billing statistics',
        tags: ['Admin - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Statistics retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getStatistics(Request $request): Response
    {
        try {
            $pdo = Database::getPdoConnection();

            // Total users with billing records
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM featherpanel_billing');
            $totalUsersWithBilling = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            // Total credits across all users
            $stmt = $pdo->query('SELECT SUM(credits) as total_credits FROM featherpanel_billing');
            $totalCredits = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['total_credits'] ?? 0);

            // Average credits per user
            $avgCredits = $totalUsersWithBilling > 0 ? round($totalCredits / $totalUsersWithBilling, 2) : 0;

            // Users with zero credits
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM featherpanel_billing WHERE credits = 0');
            $usersWithZeroCredits = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            // Users with credits > 0
            $usersWithCredits = $totalUsersWithBilling - $usersWithZeroCredits;

            $currency = CurrencyHelper::getDefaultCurrency();
            $creditsMode = CurrencyHelper::getCreditsMode();

            return ApiResponse::success([
                'users' => [
                    'total_with_billing' => $totalUsersWithBilling,
                    'with_credits' => $usersWithCredits,
                    'with_zero_credits' => $usersWithZeroCredits,
                ],
                'credits' => [
                    'total' => $totalCredits,
                    'total_formatted' => CurrencyHelper::formatAmount($totalCredits),
                    'average_per_user' => $avgCredits,
                    'average_per_user_formatted' => CurrencyHelper::formatAmount($avgCredits),
                ],
                'currency' => $currency,
                'credits_mode' => $creditsMode,
            ], 'Statistics retrieved successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage(), 'GET_STATISTICS_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/billingcore/billing-info',
        summary: 'Get admin billing info',
        description: 'Get the admin\'s billing information (stored in PluginSettings)',
        tags: ['Admin - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Billing info retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getAdminBillingInfo(Request $request): Response
    {
        $billingInfoJson = PluginSettings::getSetting('billingcore', 'admin_billing_info');
        $billingInfo = null;
        if ($billingInfoJson !== null && $billingInfoJson !== '') {
            // PluginSettings stores values HTML-escaped, so decode entities before json_decode.
            $decoded = html_entity_decode($billingInfoJson, ENT_QUOTES, 'UTF-8');
            $parsed = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $billingInfo = $parsed;
            }
        }

        // Return the billing info row directly as data (for consistency with user billing endpoint)
        return ApiResponse::success(
            $billingInfo ?: [
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
            ],
            'Billing info retrieved successfully',
            200
        );
    }

    #[OA\Patch(
        path: '/api/admin/billingcore/billing-info',
        summary: 'Update admin billing info',
        description: 'Update the admin\'s billing information (stored in PluginSettings)',
        tags: ['Admin - Billing Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', nullable: true),
                    new OA\Property(property: 'company_name', type: 'string', nullable: true),
                    new OA\Property(property: 'address_line1', type: 'string', nullable: true),
                    new OA\Property(property: 'address_line2', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string', nullable: true),
                    new OA\Property(property: 'state', type: 'string', nullable: true),
                    new OA\Property(property: 'postal_code', type: 'string', nullable: true),
                    new OA\Property(property: 'country_code', type: 'string', nullable: true),
                    new OA\Property(property: 'vat_id', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Billing info updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function updateAdminBillingInfo(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
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

        // Save to PluginSettings
        PluginSettings::setSetting('billingcore', 'admin_billing_info', json_encode($billingInfo));

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_update_admin_billing_info',
            'context' => 'Updated admin billing information',
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'billing_info' => $billingInfo,
        ], 'Billing info updated successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingcore/users/{userId}/billing-info',
        summary: 'Get user billing info',
        description: 'Get billing information for a specific user',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User billing info retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function getUserBillingInfo(Request $request, int $userId): Response
    {
        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $billingInfo = UserBillingInfo::getByUserId($userId);

        return ApiResponse::success([
            'user_id' => $userId,
            'username' => $user['username'],
            'email' => $user['email'],
            'billing_info' => $billingInfo ?: null,
        ], 'User billing info retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingcore/users/{userId}/billing-info',
        summary: 'Update user billing info',
        description: 'Update billing information for a specific user',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', nullable: true),
                    new OA\Property(property: 'company_name', type: 'string', nullable: true),
                    new OA\Property(property: 'address_line1', type: 'string', nullable: true),
                    new OA\Property(property: 'address_line2', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string', nullable: true),
                    new OA\Property(property: 'state', type: 'string', nullable: true),
                    new OA\Property(property: 'postal_code', type: 'string', nullable: true),
                    new OA\Property(property: 'country_code', type: 'string', nullable: true),
                    new OA\Property(property: 'vat_id', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User billing info updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function updateUserBillingInfo(Request $request, int $userId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        if (!UserBillingInfo::createOrUpdate($userId, $data)) {
            return ApiResponse::error('Failed to update billing info', 'UPDATE_BILLING_INFO_FAILED', 500);
        }

        $billingInfo = UserBillingInfo::getByUserId($userId);

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_update_user_billing_info',
            'context' => "Updated billing info for user: {$user['username']} (ID: {$userId})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'user_id' => $userId,
            'billing_info' => $billingInfo,
        ], 'User billing info updated successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingcore/invoices',
        summary: 'List all invoices',
        description: 'Get a list of all invoices (admin)',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'pending', 'paid', 'overdue', 'cancelled'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoices retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function listInvoices(Request $request): Response
    {
        $userId = $request->query->get('user_id');
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $pdo = Database::getPdoConnection();
        $invoiceTable = Invoice::getTableName();
        $sql = 'SELECT i.*, u.username, u.email FROM ' . $invoiceTable . ' i LEFT JOIN featherpanel_users u ON i.user_id = u.id WHERE 1=1';
        $params = [];

        if ($userId !== null && $userId > 0) {
            $sql .= ' AND i.user_id = :user_id';
            $params['user_id'] = (int) $userId;
        }

        if ($status !== null && in_array($status, ['draft', 'pending', 'paid', 'overdue', 'cancelled'], true)) {
            $sql .= ' AND i.status = :status';
            $params['status'] = $status;
        }

        // Search by username, email, invoice number, or invoice ID
        if ($search !== null && $search !== '') {
            $search = trim($search);
            if (is_numeric($search)) {
                // If search is numeric, try invoice ID or user ID
                $sql .= ' AND (i.id = :search_id OR i.user_id = :search_user_id OR i.invoice_number LIKE :search_invoice)';
                $params['search_id'] = (int) $search;
                $params['search_user_id'] = (int) $search;
                $params['search_invoice'] = '%' . $search . '%';
            } else {
                // Search by username, email, or invoice number
                $sql .= ' AND (u.username LIKE :search OR u.email LIKE :search_email OR i.invoice_number LIKE :search_invoice)';
                $params['search'] = '%' . $search . '%';
                $params['search_email'] = '%' . $search . '%';
                $params['search_invoice'] = '%' . $search . '%';
            }
        }

        // Count total
        $countSql = 'SELECT COUNT(*) as count FROM ' . $invoiceTable . ' i LEFT JOIN featherpanel_users u ON i.user_id = u.id WHERE 1=1';
        $countParams = [];
        if (isset($params['user_id'])) {
            $countSql .= ' AND i.user_id = :user_id';
            $countParams['user_id'] = $params['user_id'];
        }
        if (isset($params['status'])) {
            $countSql .= ' AND i.status = :status';
            $countParams['status'] = $params['status'];
        }
        // Add search conditions to count query
        if ($search !== null && $search !== '') {
            $search = trim($search);
            if (is_numeric($search)) {
                $countSql .= ' AND (i.id = :search_id OR i.user_id = :search_user_id OR i.invoice_number LIKE :search_invoice)';
                $countParams['search_id'] = (int) $search;
                $countParams['search_user_id'] = (int) $search;
                $countParams['search_invoice'] = '%' . $search . '%';
            } else {
                $countSql .= ' AND (u.username LIKE :search OR u.email LIKE :search_email OR i.invoice_number LIKE :search_invoice)';
                $countParams['search'] = '%' . $search . '%';
                $countParams['search_email'] = '%' . $search . '%';
                $countParams['search_invoice'] = '%' . $search . '%';
            }
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);

        // Get invoices
        $sql .= ' ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $currency = CurrencyHelper::getDefaultCurrency();
        $creditsMode = CurrencyHelper::getCreditsMode();

        // Format amounts
        foreach ($invoices as &$invoice) {
            $invoice['subtotal_formatted'] = CurrencyHelper::formatAmount((float) $invoice['subtotal']);
            $invoice['tax_amount_formatted'] = CurrencyHelper::formatAmount((float) $invoice['tax_amount']);
            $invoice['total_formatted'] = CurrencyHelper::formatAmount((float) $invoice['total']);
        }

        return ApiResponse::success([
            'data' => $invoices,
            'meta' => [
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $limit),
                ],
                'currency' => $currency,
                'credits_mode' => $creditsMode,
            ],
        ], 'Invoices retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingcore/invoices/{invoiceId}',
        summary: 'Get invoice details (Admin)',
        description: 'Get a specific invoice with all items',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function getInvoice(Request $request, int $invoiceId): Response
    {
        $invoice = BillingHelper::getInvoiceWithItems($invoiceId);
        if ($invoice === null) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $user = User::getUserById((int) $invoice['user_id']);
        $currency = CurrencyHelper::getDefaultCurrency();
        $creditsMode = CurrencyHelper::getCreditsMode();

        $invoice['subtotal_formatted'] = CurrencyHelper::formatAmount((float) $invoice['subtotal']);
        $invoice['tax_amount_formatted'] = CurrencyHelper::formatAmount((float) $invoice['tax_amount']);
        $invoice['total_formatted'] = CurrencyHelper::formatAmount((float) $invoice['total']);

        foreach ($invoice['items'] as &$item) {
            $item['unit_price_formatted'] = CurrencyHelper::formatAmount((float) $item['unit_price']);
            $item['total_formatted'] = CurrencyHelper::formatAmount((float) $item['total']);
        }

        // Get customer billing info
        $customerBillingInfo = BillingHelper::getUserBillingInfoOrDefault((int) $invoice['user_id']);

        // Get admin billing info
        $adminBillingInfo = BillingHelper::getAdminBillingInfo();

        // Merge customer and admin info into invoice response
        $invoice['customer'] = [
            'billing_info' => $customerBillingInfo,
            'username' => $user ? ($user['username'] ?? null) : null,
            'email' => $user ? ($user['email'] ?? null) : null,
        ];
        $invoice['admin'] = [
            'billing_info' => $adminBillingInfo,
        ];

        return ApiResponse::success([
            'invoice' => $invoice,
            'user' => $user ? ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email']] : null,
            'currency' => $currency,
            'credits_mode' => $creditsMode,
        ], 'Invoice retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingcore/invoices',
        summary: 'Create invoice (Admin)',
        description: 'Create a new invoice for a user',
        tags: ['Admin - Billing Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'invoice_number', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'pending', 'paid', 'overdue', 'cancelled']),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'tax_rate', type: 'number', format: 'float'),
                    new OA\Property(property: 'currency_code', type: 'string'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'quantity', type: 'number', format: 'float'),
                            new OA\Property(property: 'unit_price', type: 'number', format: 'float'),
                            new OA\Property(property: 'sort_order', type: 'integer'),
                        ]
                    )),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Invoice created successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function createInvoice(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            return ApiResponse::error('Invalid user_id', 'INVALID_USER_ID', 400);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        // Check if user has billing info
        if (!BillingHelper::canCreateInvoice($userId)) {
            return ApiResponse::error('User must have billing information before creating invoices', 'BILLING_INFO_REQUIRED', 400);
        }

        $items = $data['items'] ?? [];
        $invoiceData = [
            'invoice_number' => $data['invoice_number'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'due_date' => $data['due_date'] ?? null,
            'tax_rate' => (float) ($data['tax_rate'] ?? 0.00),
            'currency_code' => CurrencyHelper::getDefaultCurrency()['code'],
            'notes' => $data['notes'] ?? null,
        ];

        $invoice = BillingHelper::createInvoiceWithItems($userId, $invoiceData, $items);
        if ($invoice === null) {
            return ApiResponse::error('Failed to create invoice', 'CREATE_INVOICE_FAILED', 500);
        }

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_create_invoice',
            'context' => "Created invoice {$invoice['invoice_number']} for user: {$user['username']} (ID: {$userId})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        $currency = CurrencyHelper::getDefaultCurrency();
        $invoice['subtotal_formatted'] = CurrencyHelper::formatAmount((float) $invoice['subtotal']);
        $invoice['tax_amount_formatted'] = CurrencyHelper::formatAmount((float) $invoice['tax_amount']);
        $invoice['total_formatted'] = CurrencyHelper::formatAmount((float) $invoice['total']);

        return ApiResponse::success($invoice, 'Invoice created successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingcore/invoices/{invoiceId}',
        summary: 'Update invoice (Admin)',
        description: 'Update an existing invoice',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'pending', 'paid', 'overdue', 'cancelled']),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'tax_rate', type: 'number', format: 'float'),
                    new OA\Property(property: 'currency_code', type: 'string'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Invoice updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function updateInvoice(Request $request, int $invoiceId): Response
    {
        $admin = $request->get('user');
        $invoice = Invoice::getById($invoiceId);
        if ($invoice === null) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        // If tax_rate changed, recalculate totals
        if (isset($data['tax_rate'])) {
            $taxRate = (float) $data['tax_rate'];
            BillingHelper::recalculateInvoiceTotals($invoiceId, $taxRate);
            unset($data['tax_rate']); // Already handled
        }

        // Remove currency_code - always use default currency from settings
        unset($data['currency_code']);

        if (!empty($data) && !Invoice::update($invoiceId, $data)) {
            return ApiResponse::error('Failed to update invoice', 'UPDATE_INVOICE_FAILED', 500);
        }

        $updatedInvoice = BillingHelper::getInvoiceWithItems($invoiceId);
        $user = User::getUserById((int) $updatedInvoice['user_id']);

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_update_invoice',
            'context' => "Updated invoice {$updatedInvoice['invoice_number']} for user: {$user['username']} (ID: {$updatedInvoice['user_id']})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        $currency = CurrencyHelper::getDefaultCurrency();
        $updatedInvoice['subtotal_formatted'] = CurrencyHelper::formatAmount((float) $updatedInvoice['subtotal']);
        $updatedInvoice['tax_amount_formatted'] = CurrencyHelper::formatAmount((float) $updatedInvoice['tax_amount']);
        $updatedInvoice['total_formatted'] = CurrencyHelper::formatAmount((float) $updatedInvoice['total']);

        return ApiResponse::success($updatedInvoice, 'Invoice updated successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingcore/invoices/{invoiceId}',
        summary: 'Delete invoice (Admin)',
        description: 'Delete an invoice and all its items',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function deleteInvoice(Request $request, int $invoiceId): Response
    {
        $admin = $request->get('user');
        $invoice = Invoice::getById($invoiceId);
        if ($invoice === null) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $invoiceNumber = $invoice['invoice_number'];
        $userId = (int) $invoice['user_id'];
        $user = User::getUserById($userId);

        if (!Invoice::delete($invoiceId)) {
            return ApiResponse::error('Failed to delete invoice', 'DELETE_INVOICE_FAILED', 500);
        }

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_delete_invoice',
            'context' => "Deleted invoice {$invoiceNumber} for user: {$user['username']} (ID: {$userId})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([], 'Invoice deleted successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingcore/invoices/{invoiceId}/items',
        summary: 'Add invoice item (Admin)',
        description: 'Add an item to an invoice',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['description'],
                properties: [
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'quantity', type: 'number', format: 'float'),
                    new OA\Property(property: 'unit_price', type: 'number', format: 'float'),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Invoice item added successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function addInvoiceItem(Request $request, int $invoiceId): Response
    {
        $admin = $request->get('user');
        $invoice = Invoice::getById($invoiceId);
        if ($invoice === null) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $data = json_decode($request->getContent(), true);
        if ($data === null || empty($data['description'])) {
            return ApiResponse::error('Invalid JSON or missing description', 'INVALID_JSON', 400);
        }

        $itemData = [
            'invoice_id' => $invoiceId,
            'description' => $data['description'],
            'quantity' => (float) ($data['quantity'] ?? 1.00),
            'unit_price' => (float) ($data['unit_price'] ?? 0.00),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        $itemId = InvoiceItem::create($itemData);
        if ($itemId === null) {
            return ApiResponse::error('Failed to create invoice item', 'CREATE_ITEM_FAILED', 500);
        }

        // Recalculate invoice totals
        BillingHelper::recalculateInvoiceTotals($invoiceId, (float) $invoice['tax_rate']);

        $item = InvoiceItem::getById($itemId);
        $user = User::getUserById((int) $invoice['user_id']);

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_add_invoice_item',
            'context' => "Added item to invoice {$invoice['invoice_number']} for user: {$user['username']} (ID: {$invoice['user_id']})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success($item, 'Invoice item added successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingcore/invoices/{invoiceId}/items/{itemId}',
        summary: 'Update invoice item (Admin)',
        description: 'Update an invoice item',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'quantity', type: 'number', format: 'float'),
                    new OA\Property(property: 'unit_price', type: 'number', format: 'float'),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Invoice item updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice or item not found'),
        ]
    )]
    public function updateInvoiceItem(Request $request, int $invoiceId, int $itemId): Response
    {
        $admin = $request->get('user');
        $invoice = Invoice::getById($invoiceId);
        if ($invoice === null) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $item = InvoiceItem::getById($itemId);
        if ($item === null || (int) $item['invoice_id'] !== $invoiceId) {
            return ApiResponse::error('Invoice item not found', 'ITEM_NOT_FOUND', 404);
        }

        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        if (!InvoiceItem::update($itemId, $data)) {
            return ApiResponse::error('Failed to update invoice item', 'UPDATE_ITEM_FAILED', 500);
        }

        // Recalculate invoice totals
        BillingHelper::recalculateInvoiceTotals($invoiceId, (float) $invoice['tax_rate']);

        $updatedItem = InvoiceItem::getById($itemId);
        $user = User::getUserById((int) $invoice['user_id']);

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_update_invoice_item',
            'context' => "Updated item in invoice {$invoice['invoice_number']} for user: {$user['username']} (ID: {$invoice['user_id']})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success($updatedItem, 'Invoice item updated successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingcore/invoices/{invoiceId}/items/{itemId}',
        summary: 'Delete invoice item (Admin)',
        description: 'Delete an invoice item',
        tags: ['Admin - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice item deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Invoice or item not found'),
        ]
    )]
    public function deleteInvoiceItem(Request $request, int $invoiceId, int $itemId): Response
    {
        $admin = $request->get('user');
        $invoice = Invoice::getById($invoiceId);
        if ($invoice === null) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $item = InvoiceItem::getById($itemId);
        if ($item === null || (int) $item['invoice_id'] !== $invoiceId) {
            return ApiResponse::error('Invoice item not found', 'ITEM_NOT_FOUND', 404);
        }

        if (!InvoiceItem::delete($itemId)) {
            return ApiResponse::error('Failed to delete invoice item', 'DELETE_ITEM_FAILED', 500);
        }

        // Recalculate invoice totals
        BillingHelper::recalculateInvoiceTotals($invoiceId, (float) $invoice['tax_rate']);

        $user = User::getUserById((int) $invoice['user_id']);

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingcore_delete_invoice_item',
            'context' => "Deleted item from invoice {$invoice['invoice_number']} for user: {$user['username']} (ID: {$invoice['user_id']})",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([], 'Invoice item deleted successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingcore/settings',
        summary: 'Get billingcore settings',
        description: 'Get general billingcore configuration settings',
        tags: ['Admin - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Settings retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getSettings(Request $request): Response
    {
        // Get credits mode (currency or token), default to 'currency'
        $creditsMode = PluginSettings::getSetting('billingcore', 'credits_mode');
        if ($creditsMode === null || $creditsMode === '') {
            $creditsMode = 'currency'; // Default to currency mode
        }

        // Get tokens per currency (only relevant for token mode)
        $tokensPerCurrency = PluginSettings::getSetting('billingcore', 'tokens_per_currency');
        if ($tokensPerCurrency === null || $tokensPerCurrency === '') {
            $tokensPerCurrency = '1'; // Default: 1 token per 1 currency unit
        }

        return ApiResponse::success([
            'credits_mode' => $creditsMode,
            'tokens_per_currency' => $tokensPerCurrency,
        ], 'Settings retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingcore/settings',
        summary: 'Update billingcore settings',
        description: 'Update general billingcore configuration settings',
        tags: ['Admin - Billing Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'credits_mode', type: 'string', enum: ['currency', 'token'], description: 'Credits mode: currency (for paid hosting) or token (for freemium hosting)'),
                    new OA\Property(property: 'tokens_per_currency', type: 'string', description: 'Number of tokens per 1 currency unit (e.g., "10" means 10 tokens = 1€). Only used when credits_mode is "token".'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function updateSettings(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $creditsMode = $data['credits_mode'] ?? null;
        if ($creditsMode !== null) {
            if (!in_array($creditsMode, ['currency', 'token'], true)) {
                return ApiResponse::error('Invalid credits_mode. Must be "currency" or "token"', 'INVALID_CREDITS_MODE', 400);
            }

            // Update setting
            PluginSettings::setSetting('billingcore', 'credits_mode', $creditsMode);

            // Log activity
            Activity::createActivity([
                'user_uuid' => $admin['uuid'] ?? null,
                'name' => 'billingcore_update_settings',
                'context' => "Updated credits mode to: {$creditsMode}",
                'ip_address' => CloudFlareRealIP::getRealIP(),
            ]);
        }

        $tokensPerCurrency = $data['tokens_per_currency'] ?? null;
        if ($tokensPerCurrency !== null) {
            // Validate it's a positive number
            if (!is_numeric($tokensPerCurrency) || (float) $tokensPerCurrency <= 0) {
                return ApiResponse::error('Invalid tokens_per_currency. Must be a positive number', 'INVALID_TOKENS_PER_CURRENCY', 400);
            }

            // Update setting
            PluginSettings::setSetting('billingcore', 'tokens_per_currency', (string) $tokensPerCurrency);

            // Log activity
            Activity::createActivity([
                'user_uuid' => $admin['uuid'] ?? null,
                'name' => 'billingcore_update_settings',
                'context' => "Updated tokens per currency to: {$tokensPerCurrency}",
                'ip_address' => CloudFlareRealIP::getRealIP(),
            ]);
        }

        // Get current settings
        $currentCreditsMode = PluginSettings::getSetting('billingcore', 'credits_mode');
        if ($currentCreditsMode === null || $currentCreditsMode === '') {
            $currentCreditsMode = 'currency';
        }

        $currentTokensPerCurrency = PluginSettings::getSetting('billingcore', 'tokens_per_currency');
        if ($currentTokensPerCurrency === null || $currentTokensPerCurrency === '') {
            $currentTokensPerCurrency = '1';
        }

        return ApiResponse::success([
            'credits_mode' => $currentCreditsMode,
            'tokens_per_currency' => $currentTokensPerCurrency,
        ], 'Settings updated successfully', 200);
    }
}
