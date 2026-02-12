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
