<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait SoftDeletableTrait
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function markAsDeleted(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }
}
