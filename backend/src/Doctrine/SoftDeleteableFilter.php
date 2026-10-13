<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class SoftDeleteableFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->hasProperty('deletedAt')) {
            return '';
        }

        return sprintf('%s.deleted_at IS NULL', $targetTableAlias);
    }
}
