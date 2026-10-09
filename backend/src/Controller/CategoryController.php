<?php

namespace App\Controller;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
