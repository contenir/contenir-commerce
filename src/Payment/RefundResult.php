<?php

declare(strict_types=1);

namespace Contenir\Commerce\Payment;

/**
 * @api
 */
final readonly class RefundResult
{
    public function __construct(
        public string $id,
        public string $status,
    ) {}
}
