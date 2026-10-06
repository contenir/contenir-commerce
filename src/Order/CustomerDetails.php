<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;

/**
 * @api
 */
final readonly class CustomerDetails
{
    /**
     * @throws InvalidArgumentException When the name or email is empty.
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $notes = null,
    ) {
        if ('' === $name || '' === $email) {
            throw new InvalidArgumentException('Customer name and email are required');
        }
    }
}
