<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * The bundle's mapped-superclass (id/refreshToken/username/valid) is already
 * declared via its own XML mapping, so this subclass only needs to anchor it
 * to a concrete entity/table — redeclaring those fields here would duplicate
 * the XML mapping and break schema generation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_token')]
class RefreshToken extends BaseRefreshToken
{
}
