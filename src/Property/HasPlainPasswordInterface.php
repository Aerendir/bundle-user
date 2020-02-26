<?php

declare(strict_types=1);

/*
 * This file is part of SHQUsersBundle.
 *
 * (c) Adamo Aerendir Crespi <aerendir@serendipityhq.com>.
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace SerendipityHQ\Bundle\UsersBundle\Property;

interface HasPlainPasswordInterface
{
    /**
     * @param string $password
     */
    public function setPlainPassword(string $password): void;

    /**
     * @return string|null
     */
    public function getPlainPassword(): ?string;
}
