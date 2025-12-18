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

namespace App\Addons\billingcore\Controllers\User;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Addons\billingcore\Chat\Invoice;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Chat\UserBillingInfo;
use App\Addons\billingcore\Helpers\BillingHelper;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingcore\Helpers\CurrencyHelper;

#[OA\Tag(name: 'User - Billing Core', description: 'Billing and credits management for users')]
class BillingCoreController
{
    #[OA\Get(
        path: '/api/user/billingcore/credits',
        summary: 'Get user credits',
        description: 'Get the current user\'s credit balance',
        tags: ['User - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Credits retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getCredits(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $credits = CreditsHelper::getUserCredits($user['id']);
        $currency = CurrencyHelper::getDefaultCurrency();

        return ApiResponse::success([
            'credits' => $credits,
            'credits_formatted' => CurrencyHelper::formatAmount($credits),
            'currency' => $currency,
        ], 'Credits retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/user/billingcore/billing',
        summary: 'Get user billing info',
        description: 'Get the current user\'s complete billing information',
        tags: ['User - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Billing info retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getBilling(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $billing = \App\Addons\billingcore\Chat\Billing::getByUserId($user['id']);
        $credits = CreditsHelper::getUserCredits($user['id']);
        $currency = CurrencyHelper::getDefaultCurrency();
        $billingInfo = UserBillingInfo::getByUserId($user['id']);

        if (!$billing) {
            // Return default structure if no billing record exists yet
            return ApiResponse::success([
                'user_id' => $user['id'],
                'credits' => $credits,
                'credits_formatted' => CurrencyHelper::formatAmount($credits),
                'currency' => $currency,
                'billing_info' => $billingInfo,
                'created_at' => null,
                'updated_at' => null,
            ], 'Billing info retrieved successfully', 200);
        }

        return ApiResponse::success([
            'user_id' => $billing['user_id'],
            'credits' => $billing['credits'],
            'credits_formatted' => CurrencyHelper::formatAmount($billing['credits']),
            'currency' => $currency,
            'billing_info' => $billingInfo,
            'created_at' => $billing['created_at'],
            'updated_at' => $billing['updated_at'],
        ], 'Billing info retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/user/billingcore/billing-info',
        summary: 'Get user billing profile',
        description: 'Get the current user\'s billing profile (name, address, VAT, etc.)',
        tags: ['User - Billing Core'],
        responses: [
            new OA\Response(response: 200, description: 'Billing profile retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getBillingInfo(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $info = UserBillingInfo::getByUserId($user['id']);

        if ($info === null) {
            return ApiResponse::success([
                'user_id' => $user['id'],
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
                'created_at' => null,
                'updated_at' => null,
            ], 'Billing profile retrieved successfully', 200);
        }

        return ApiResponse::success($info, 'Billing profile retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/user/billingcore/billing-info',
        summary: 'Update user billing profile',
        description: 'Create or update the current user\'s billing profile',
        tags: ['User - Billing Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', description: 'Full legal name'),
                    new OA\Property(property: 'company_name', type: 'string', nullable: true),
                    new OA\Property(property: 'address_line1', type: 'string', description: 'Address line 1'),
                    new OA\Property(property: 'address_line2', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'state', type: 'string', nullable: true),
                    new OA\Property(property: 'postal_code', type: 'string'),
                    new OA\Property(property: 'country_code', type: 'string', description: 'ISO 3166-1 alpha-2 country code'),
                    new OA\Property(property: 'vat_id', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Billing profile updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function updateBillingInfo(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        // Basic validation for required fields on first-time creation happens in chat layer,
        // but we can still ensure some basic string checks here.
        foreach (['full_name', 'address_line1', 'city', 'postal_code', 'country_code'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (!UserBillingInfo::createOrUpdate($user['id'], $data)) {
            return ApiResponse::error('Failed to update billing profile', 'UPDATE_BILLING_PROFILE_FAILED', 400);
        }

        $info = UserBillingInfo::getByUserId($user['id']);

        return ApiResponse::success($info, 'Billing profile updated successfully', 200);
    }

    #[OA\Get(
        path: '/api/user/billingcore/invoices',
        summary: 'List user invoices',
        description: 'Get a list of invoices for the current user',
        tags: ['User - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'pending', 'paid', 'overdue', 'cancelled'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoices retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function listInvoices(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $status = $request->query->get('status');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $invoices = Invoice::getByUserId($user['id'], $status, $limit, $offset);
        $total = Invoice::countByUserId($user['id'], $status);
        $currency = CurrencyHelper::getDefaultCurrency();

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
            ],
        ], 'Invoices retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/user/billingcore/invoices/{invoiceId}',
        summary: 'Get invoice details',
        description: 'Get a specific invoice with all items',
        tags: ['User - Billing Core'],
        parameters: [
            new OA\Parameter(name: 'invoiceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function getInvoice(Request $request, int $invoiceId): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $invoice = Invoice::getById($invoiceId);
        if ($invoice === null || (int) $invoice['user_id'] !== $user['id']) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $invoiceWithItems = BillingHelper::getInvoiceWithItems($invoiceId);
        if ($invoiceWithItems === null) {
            return ApiResponse::error('Invoice not found', 'INVOICE_NOT_FOUND', 404);
        }

        $currency = CurrencyHelper::getDefaultCurrency();
        $invoiceWithItems['subtotal_formatted'] = CurrencyHelper::formatAmount((float) $invoiceWithItems['subtotal']);
        $invoiceWithItems['tax_amount_formatted'] = CurrencyHelper::formatAmount((float) $invoiceWithItems['tax_amount']);
        $invoiceWithItems['total_formatted'] = CurrencyHelper::formatAmount((float) $invoiceWithItems['total']);

        foreach ($invoiceWithItems['items'] as &$item) {
            $item['unit_price_formatted'] = CurrencyHelper::formatAmount((float) $item['unit_price']);
            $item['total_formatted'] = CurrencyHelper::formatAmount((float) $item['total']);
        }

        // Get customer billing info
        $customerBillingInfo = BillingHelper::getUserBillingInfo($user['id']);

        // Get admin billing info
        $adminBillingInfo = BillingHelper::getAdminBillingInfo();

        // Merge customer and admin info into invoice response
        $invoiceWithItems['customer'] = [
            'billing_info' => $customerBillingInfo,
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
        ];
        $invoiceWithItems['admin'] = [
            'billing_info' => $adminBillingInfo,
        ];

        return ApiResponse::success($invoiceWithItems, 'Invoice retrieved successfully', 200);
    }
}
