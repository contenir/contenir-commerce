<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Model\Entity\AbstractOrderEntity;

/**
 * @api
 */
final readonly class CompletionResult
{
    /**
     * @param list<string> $unavailableTitles titles that were lost to
     *     another buyer when the outcome is RefundedRace
     */
    public function __construct(
        public AbstractOrderEntity $order,
        public CompletionOutcome $outcome,
        public array $unavailableTitles = [],
    ) {}
}
