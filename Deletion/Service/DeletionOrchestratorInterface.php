<?php

declare(strict_types=1);

namespace Shared\Deletion\Service;

use Shared\Deletion\Dto\{OrderedPlanDto, RelationsDto};

interface DeletionOrchestratorInterface
{
    public function execute(object $root, bool $dryRun = false): void;
    public function plan(object $root): RelationsDto;
    public function getOrderedPlan(object $root): OrderedPlanDto;
}