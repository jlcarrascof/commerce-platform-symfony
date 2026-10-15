<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
class UserController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/api/users', name: 'user_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->orderBy('u.email', 'ASC');

        $totalCount = (int) (clone $qb)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $users = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $response = new JsonResponse(array_map($this->serialize(...), $users));
        $response->headers->set('X-Total-Count', (string) $totalCount);

        return $response;
    }

    #[Route('/api/users/deleted', name: 'user_list_deleted', methods: ['GET'])]
    public function listDeleted(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $filters = $this->entityManager->getFilters();
        $filters->disable('soft_deleteable');

        $qb = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->andWhere('u.deletedAt IS NOT NULL')
            ->orderBy('u.deletedAt', 'DESC');

        $totalCount = (int) (clone $qb)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $users = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $filters->enable('soft_deleteable');

        $response = new JsonResponse(array_map($this->serialize(...), $users));
        $response->headers->set('X-Total-Count', (string) $totalCount);

        return $response;
    }

    #[Route('/api/users', name: 'user_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $isAdmin = true === ($data['isAdmin'] ?? false);
        $fullName = (string) ($data['fullName'] ?? '');

        if ('' !== $email && null !== $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            return new JsonResponse(['errors' => [['field' => 'email', 'message' => 'This email is already in use.']]], 422);
        }

        if (!$isAdmin && '' === trim($fullName)) {
            return new JsonResponse(['errors' => [['field' => 'fullName', 'message' => 'Full name is required for customer accounts.']]], 422);
        }

        $errors = [];
        if ('' === $password) {
            $errors[] = ['field' => 'password', 'message' => 'Password should not be blank.'];
        }

        $user = new User($email, '');
        $user->setPassword('placeholder');

        $violations = $this->validator->validate($user);
        $errors = [...$errors, ...$this->formatViolations($violations)];
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => $errors], 422);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles($isAdmin ? ['ROLE_ADMIN'] : []);

        $this->entityManager->persist($user);

        if (!$isAdmin) {
            $customer = new Customer($fullName, $user);
            $this->entityManager->persist($customer);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serialize($user), 201);
    }

    #[Route('/api/users/{id}', name: 'user_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (null === $user) {
            return new JsonResponse(['message' => 'User not found.'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $email = (string) ($data['email'] ?? $user->getEmail());
        $isAdmin = true === ($data['isAdmin'] ?? in_array('ROLE_ADMIN', $user->getRoles(), true));
        $fullName = array_key_exists('fullName', $data) ? (string) $data['fullName'] : null;
        $password = (string) ($data['password'] ?? '');

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null !== $existing && $existing->getId() !== $user->getId()) {
            return new JsonResponse(['errors' => [['field' => 'email', 'message' => 'This email is already in use.']]], 422);
        }

        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['user' => $user]);

        if (!$isAdmin && '' === trim($fullName ?? ($customer?->getFullName() ?? ''))) {
            return new JsonResponse(['errors' => [['field' => 'fullName', 'message' => 'Full name is required for customer accounts.']]], 422);
        }

        $user->setEmail($email);
        $user->setRoles($isAdmin ? ['ROLE_ADMIN'] : []);

        if ('' !== $password) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            return new JsonResponse(['errors' => $this->formatViolations($violations)], 422);
        }

        if (!$isAdmin) {
            if (null === $customer) {
                $customer = new Customer($fullName ?? '', $user);
                $this->entityManager->persist($customer);
            } else {
                $customer->setFullName($fullName ?? $customer->getFullName());
            }
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serialize($user));
    }

    #[Route('/api/users/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] ?UserInterface $currentUser): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (null === $user) {
            return new JsonResponse(['message' => 'User not found.'], 404);
        }

        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            return new JsonResponse(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $user->markAsDeleted();
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/users/{id}/restore', name: 'user_restore', methods: ['POST'])]
    public function restore(int $id): JsonResponse
    {
        $filters = $this->entityManager->getFilters();
        $filters->disable('soft_deleteable');
        $user = $this->entityManager->getRepository(User::class)->find($id);
        $filters->enable('soft_deleteable');

        if (null === $user) {
            return new JsonResponse(['message' => 'User not found.'], 404);
        }

        $user->restore();
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($user));
    }

    /** @return array{id: ?int, email: string, isAdmin: bool, fullName: ?string} */
    private function serialize(User $user): array
    {
        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['user' => $user]);

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles(), true),
            'fullName' => $customer?->getFullName(),
        ];
    }

    /**
     * @param iterable<\Symfony\Component\Validator\ConstraintViolationInterface> $violations
     *
     * @return list<array{field: string, message: string}>
     */
    private function formatViolations(iterable $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $errors;
    }
}
