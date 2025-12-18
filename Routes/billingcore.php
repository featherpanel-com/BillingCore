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

use App\App;
use App\Permissions;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\billingcore\Controllers\User\BillingCoreController as UserController;
use App\Addons\billingcore\Controllers\Admin\BillingCoreController as AdminController;

return function (RouteCollection $routes): void {
    // User Routes (require authentication)
    // Get user's own credits
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingcore-user-credits',
        '/api/user/billingcore/credits',
        function (Request $request) {
            return (new UserController())->getCredits($request);
        },
        ['GET']
    );

    // Get user's combined billing + credits info
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingcore-user-billing',
        '/api/user/billingcore/billing',
        function (Request $request) {
            return (new UserController())->getBilling($request);
        },
        ['GET']
    );

    // Get user's billing profile
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingcore-user-billing-info',
        '/api/user/billingcore/billing-info',
        function (Request $request) {
            return (new UserController())->getBillingInfo($request);
        },
        ['GET']
    );

    // Update user's billing profile
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingcore-user-billing-info-update',
        '/api/user/billingcore/billing-info',
        function (Request $request) {
            return (new UserController())->updateBillingInfo($request);
        },
        ['PATCH', 'PUT']
    );

    // Admin Routes
    // Get all users with credits
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-users',
        '/api/admin/billingcore/users',
        function (Request $request) {
            return (new AdminController())->getUsers($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Get single user credits
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-user-credits',
        '/api/admin/billingcore/users/{userId}/credits',
        function (Request $request, array $args) {
            $userId = (int) ($args['userId'] ?? 0);
            if (!$userId) {
                return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getUserCredits($request, $userId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Add credits to user
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-user-add-credits',
        '/api/admin/billingcore/users/{userId}/credits/add',
        function (Request $request, array $args) {
            $userId = (int) ($args['userId'] ?? 0);
            if (!$userId) {
                return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->addUserCredits($request, $userId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Remove credits from user
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-user-remove-credits',
        '/api/admin/billingcore/users/{userId}/credits/remove',
        function (Request $request, array $args) {
            $userId = (int) ($args['userId'] ?? 0);
            if (!$userId) {
                return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->removeUserCredits($request, $userId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Set credits for user
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-user-set-credits',
        '/api/admin/billingcore/users/{userId}/credits/set',
        function (Request $request, array $args) {
            $userId = (int) ($args['userId'] ?? 0);
            if (!$userId) {
                return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->setUserCredits($request, $userId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST', 'PUT']
    );

    // Search users
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-search-users',
        '/api/admin/billingcore/users/search',
        function (Request $request) {
            return (new AdminController())->searchUsers($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Get currency settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-currency-settings',
        '/api/admin/billingcore/currency/settings',
        function (Request $request) {
            return (new AdminController())->getCurrencySettings($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update currency settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-currency-settings-update',
        '/api/admin/billingcore/currency/settings',
        function (Request $request) {
            return (new AdminController())->updateCurrencySettings($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // List available currencies
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-currencies-list',
        '/api/admin/billingcore/currency/list',
        function (Request $request) {
            return (new AdminController())->listCurrencies($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Get statistics
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-statistics',
        '/api/admin/billingcore/statistics',
        function (Request $request) {
            return (new AdminController())->getStatistics($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Get admin billing info
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-billing-info',
        '/api/admin/billingcore/billing-info',
        function (Request $request) {
            return (new AdminController())->getAdminBillingInfo($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update admin billing info
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-billing-info-update',
        '/api/admin/billingcore/billing-info',
        function (Request $request) {
            return (new AdminController())->updateAdminBillingInfo($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Get billingcore settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-settings',
        '/api/admin/billingcore/settings',
        function (Request $request) {
            return (new AdminController())->getSettings($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update billingcore settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-settings-update',
        '/api/admin/billingcore/settings',
        function (Request $request) {
            return (new AdminController())->updateSettings($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Get user billing info
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-user-billing-info',
        '/api/admin/billingcore/users/{userId}/billing-info',
        function (Request $request, array $args) {
            $userId = (int) ($args['userId'] ?? 0);
            if (!$userId) {
                return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getUserBillingInfo($request, $userId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update user billing info
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-user-billing-info-update',
        '/api/admin/billingcore/users/{userId}/billing-info',
        function (Request $request, array $args) {
            $userId = (int) ($args['userId'] ?? 0);
            if (!$userId) {
                return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->updateUserBillingInfo($request, $userId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // User Invoice Routes
    // List user invoices
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingcore-user-invoices-list',
        '/api/user/billingcore/invoices',
        function (Request $request) {
            return (new UserController())->listInvoices($request);
        },
        ['GET']
    );

    // Get user invoice details
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingcore-user-invoice',
        '/api/user/billingcore/invoices/{invoiceId}',
        function (Request $request, array $args) {
            $invoiceId = (int) ($args['invoiceId'] ?? 0);
            if (!$invoiceId) {
                return ApiResponse::error('Invalid invoice ID', 'INVALID_ID', 400);
            }

            return (new UserController())->getInvoice($request, $invoiceId);
        },
        ['GET']
    );

    // Admin Invoice Routes
    // List all invoices
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoices-list',
        '/api/admin/billingcore/invoices',
        function (Request $request) {
            return (new AdminController())->listInvoices($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Get invoice details
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoice',
        '/api/admin/billingcore/invoices/{invoiceId}',
        function (Request $request, array $args) {
            $invoiceId = (int) ($args['invoiceId'] ?? 0);
            if (!$invoiceId) {
                return ApiResponse::error('Invalid invoice ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getInvoice($request, $invoiceId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Create invoice
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoice-create',
        '/api/admin/billingcore/invoices',
        function (Request $request) {
            return (new AdminController())->createInvoice($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Update invoice
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoice-update',
        '/api/admin/billingcore/invoices/{invoiceId}',
        function (Request $request, array $args) {
            $invoiceId = (int) ($args['invoiceId'] ?? 0);
            if (!$invoiceId) {
                return ApiResponse::error('Invalid invoice ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->updateInvoice($request, $invoiceId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Delete invoice
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoice-delete',
        '/api/admin/billingcore/invoices/{invoiceId}',
        function (Request $request, array $args) {
            $invoiceId = (int) ($args['invoiceId'] ?? 0);
            if (!$invoiceId) {
                return ApiResponse::error('Invalid invoice ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->deleteInvoice($request, $invoiceId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['DELETE']
    );

    // Add invoice item
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoice-item-add',
        '/api/admin/billingcore/invoices/{invoiceId}/items',
        function (Request $request, array $args) {
            $invoiceId = (int) ($args['invoiceId'] ?? 0);
            if (!$invoiceId) {
                return ApiResponse::error('Invalid invoice ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->addInvoiceItem($request, $invoiceId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Update invoice item
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoice-item-update',
        '/api/admin/billingcore/invoices/{invoiceId}/items/{itemId}',
        function (Request $request, array $args) {
            $invoiceId = (int) ($args['invoiceId'] ?? 0);
            $itemId = (int) ($args['itemId'] ?? 0);
            if (!$invoiceId || !$itemId) {
                return ApiResponse::error('Invalid invoice or item ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->updateInvoiceItem($request, $invoiceId, $itemId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Delete invoice item
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingcore-admin-invoice-item-delete',
        '/api/admin/billingcore/invoices/{invoiceId}/items/{itemId}',
        function (Request $request, array $args) {
            $invoiceId = (int) ($args['invoiceId'] ?? 0);
            $itemId = (int) ($args['itemId'] ?? 0);
            if (!$invoiceId || !$itemId) {
                return ApiResponse::error('Invalid invoice or item ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->deleteInvoiceItem($request, $invoiceId, $itemId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['DELETE']
    );
};
