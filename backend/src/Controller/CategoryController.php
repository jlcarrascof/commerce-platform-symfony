<?php

namespace App\Controller;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CategoryController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/categories', name: 'category_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAll();

        return new JsonResponse(array_map($this->serialize(...), $categories));
    }

    #[Route('/api/categories', name: 'category_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $category = new Category(
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
        );

        $violations = $this->validator->validate($category);
        if (count($violations) > 0) {
            return new JsonResponse(['errors' => $this->formatViolations($violations)], 422);
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($category), 201);
    }

    #[Route('/api/categories/{id}', name: 'category_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $category = $this->entityManager->getRepository(Category::class)->find($id);

        if (null === $category) {
            return new JsonResponse(['message' => 'Category not found.'], 404);
        }

        $category->markAsDeleted();
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/categories/deleted', name: 'category_list_deleted', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listDeleted(): JsonResponse
    {
        $filters = $this->entityManager->getFilters();
        $filters->disable('soft_deleteable');

        $categories = $this->entityManager->getRepository(Category::class)->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NOT NULL')
            ->orderBy('c.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $filters->enable('soft_deleteable');

        return new JsonResponse(array_map($this->serialize(...), $categories));
    }

    #[Route('/api/categories/{id}/restore', name: 'category_restore', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function restore(int $id): JsonResponse
    {
        $filters = $this->entityManager->getFilters();
        $filters->disable('soft_deleteable');
        $category = $this->entityManager->getRepository(Category::class)->find($id);
        $filters->enable('soft_deleteable');

        if (null === $category) {
            return new JsonResponse(['message' => 'Category not found.'], 404);
        }

        $category->restore();
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($category));
    }

    /** @return array{id: ?int, name: string, slug: string} */
    private function serialize(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
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
