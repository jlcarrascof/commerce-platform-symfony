<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/products', name: 'product_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $products = $this->entityManager->getRepository(Product::class)->findAll();

        return new JsonResponse(array_map($this->serialize(...), $products));
    }

    #[Route('/api/products', name: 'product_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $category = isset($data['categoryId'])
            ? $this->entityManager->getRepository(Category::class)->find($data['categoryId'])
            : null;

        if (null === $category) {
            return new JsonResponse(['errors' => [['field' => 'categoryId', 'message' => 'Category not found.']]], 422);
        }

        $product = new Product(
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            priceInCents: (int) ($data['priceInCents'] ?? 0),
            stock: (int) ($data['stock'] ?? 0),
            imageUrl: (string) ($data['imageUrl'] ?? ''),
            category: $category,
        );

        $violations = $this->validator->validate($product);
        if (count($violations) > 0) {
            return new JsonResponse(['errors' => $this->formatViolations($violations)], 422);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($product), 201);
    }

    private function serialize(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'priceInCents' => $product->getPriceInCents(),
            'stock' => $product->getStock(),
            'imageUrl' => $product->getImageUrl(),
            'category' => [
                'id' => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName(),
                'slug' => $product->getCategory()->getSlug(),
            ],
        ];
    }

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
