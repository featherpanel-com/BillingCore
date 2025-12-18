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

use App\Addons\billingcore\Chat\Billing;

/**
 * Helper for working with user billing credits inside the billingcore addon.
 *
 * This wraps the Billing chat model with a small, expressive API.
 */
class CreditsHelper
{
    /**
     * Get the current credit balance for a user.
     */
    public static function getUserCredits(int $userId): int
    {
        return Billing::getCredits($userId);
    }

    /**
     * Add credits to a user's balance.
     *
     * @return bool true on success, false on failure
     */
    public static function addUserCredits(int $userId, int $amount): bool
    {
        return Billing::addCredits($userId, $amount);
    }

    /**
     * Remove credits from a user's balance.
     *
     * This will never allow the balance to go below zero.
     *
     * @return bool true on success, false if insufficient credits or on error
     */
    public static function removeUserCredits(int $userId, int $amount): bool
    {
        return Billing::removeCredits($userId, $amount);
    }
}
