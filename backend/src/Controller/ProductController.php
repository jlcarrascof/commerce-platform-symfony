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
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $categorySlug = $request->query->get('category');
        $sort = (string) $request->query->get('sort', 'name');

        $qb = $this->entityManager->getRepository(Product::class)->createQueryBuilder('p')
            ->join('p.category', 'c');

        if (null !== $categorySlug && '' !== $categorySlug) {
            $qb->andWhere('c.slug = :categorySlug')->setParameter('categorySlug', $categorySlug);
        }

        $sortFields = [
            'name' => 'p.name ASC',
            '-name' => 'p.name DESC',
            'price' => 'p.priceInCents ASC',
            '-price' => 'p.priceInCents DESC',
        ];
        $qb->orderBy(...explode(' ', $sortFields[$sort] ?? $sortFields['name']));

        $totalCount = (clone $qb)->select('COUNT(p.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $products = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $response = new JsonResponse(array_map($this->serialize(...), $products));
        $response->headers->set('X-Total-Count', (string) $totalCount);

        return $response;
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

    /**
     * @return array{id: ?int, name: string, slug: string, description: string, priceInCents: int, stock: int, imageUrl: string, category: array{id: ?int, name: string, slug: string}}
     */
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
