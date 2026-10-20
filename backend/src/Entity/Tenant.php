<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

enum TenantPlan: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Enterprise = 'enterprise';
}

#[ORM\Entity]
class Tenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $name;

    #[ORM\Column(length: 160, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Slug must be lowercase, alphanumeric and dash-separated.')]
    private string $slug;

    #[ORM\Column(length: 20, enumType: TenantPlan::class)]
    private TenantPlan $plan;

    public function __construct(string $name, string $slug, TenantPlan $plan = TenantPlan::Free)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->plan = $plan;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getPlan(): TenantPlan
    {
        return $this->plan;
    }

    public function setPlan(TenantPlan $plan): void
    {
        $this->plan = $plan;
    }
}
