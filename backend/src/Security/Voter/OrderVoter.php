<?php

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use App\Tenant\TenantContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Order>
 */
class OrderVoter extends Voter
{
    public const CONFIRM = 'ORDER_CONFIRM';
    public const CANCEL = 'ORDER_CANCEL';

    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CONFIRM, self::CANCEL], true) && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Order $order */
        $order = $subject;

        $currentTenant = $this->tenantContext->getTenant();
        if (null === $currentTenant || $order->getTenant()->getId() !== $currentTenant->getId()) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $order->getCustomer()->getUser()->getId() === $user->getId();
    }
}
