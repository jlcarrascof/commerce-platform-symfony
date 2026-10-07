<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

class LoginController
{
    #[Route('/api/login', name: 'login_check', methods: ['POST'])]
    public function __invoke(): never
    {
        // Intercepted by the "login" firewall's json_login listener before reaching here.
        throw new \LogicException('This route should never be reached, it is handled by the JWT authenticator.');
    }
}
