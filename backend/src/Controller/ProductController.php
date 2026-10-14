<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ProductController
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly TagAwareCacheInterface $productsCache,
    ) {
    }

    #[Route('/api/products', name: 'product_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $categorySlug = (string) $request->query->get('category', '');
        $sort = (string) $request->query->get('sort', 'name');

        $cacheKey = 'products_list_'.md5(serialize([$page, $limit, $categorySlug, $sort]));
        $isHit = true;

        /** @var array{items: list<array{id: ?int, name: string, slug: string, description: string, priceInCents: int, stock: int, imageUrl: string, category: array{id: ?int, name: string, slug: string}}>, totalCount: int} $payload */
        $payload = $this->productsCache->get(
            $cacheKey,
            function (ItemInterface $item) use (&$isHit, $categorySlug, $sort, $page, $limit): array {
                $isHit = false;
                $item->tag('products');
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                $qb = $this->entityManager->getRepository(Product::class)->createQueryBuilder('p')
                    ->join('p.category', 'c');

                if ('' !== $categorySlug) {
                    $qb->andWhere('c.slug = :categorySlug')->setParameter('categorySlug', $categorySlug);
                }

                $sortFields = [
                    'name' => 'p.name ASC',
                    '-name' => 'p.name DESC',
                    'price' => 'p.priceInCents ASC',
                    '-price' => 'p.priceInCents DESC',
                ];
                $qb->orderBy(...explode(' ', $sortFields[$sort] ?? $sortFields['name']));

                $totalCount = (int) (clone $qb)->select('COUNT(p.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

                $products = $qb
                    ->setFirstResult(($page - 1) * $limit)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();

                return [
                    'items' => array_map($this->serialize(...), $products),
                    'totalCount' => $totalCount,
                ];
            },
        );

        $response = new JsonResponse($payload['items']);
        $response->headers->set('X-Total-Count', (string) $payload['totalCount']);
        $response->headers->set('X-Cache', $isHit ? 'HIT' : 'MISS');

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
        $this->productsCache->invalidateTags(['products']);

        return new JsonResponse($this->serialize($product), 201);
    }

    #[Route('/api/products/{id}', name: 'product_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $product = $this->entityManager->getRepository(Product::class)->find($id);

        if (null === $product) {
            return new JsonResponse(['message' => 'Product not found.'], 404);
        }

        $product->markAsDeleted();
        $this->entityManager->flush();
        $this->productsCache->invalidateTags(['products']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/products/deleted', name: 'product_list_deleted', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listDeleted(): JsonResponse
    {
        $filters = $this->entityManager->getFilters();
        $filters->disable('soft_deleteable');

        $products = $this->entityManager->getRepository(Product::class)->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NOT NULL')
            ->orderBy('p.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $filters->enable('soft_deleteable');

        return new JsonResponse(array_map($this->serialize(...), $products));
    }

    #[Route('/api/products/{id}/restore', name: 'product_restore', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function restore(int $id): JsonResponse
    {
        $filters = $this->entityManager->getFilters();
        $filters->disable('soft_deleteable');
        $product = $this->entityManager->getRepository(Product::class)->find($id);
        $filters->enable('soft_deleteable');

        if (null === $product) {
            return new JsonResponse(['message' => 'Product not found.'], 404);
        }

        $product->restore();
        $this->entityManager->flush();
        $this->productsCache->invalidateTags(['products']);

        return new JsonResponse($this->serialize($product));
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
