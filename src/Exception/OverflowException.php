<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use OverflowException as SplOverflowException;

use function sprintf;

/**
 * Money arithmetic would exceed the largest integer number of cents.
 *
 * @api
 */
final class OverflowException extends SplOverflowException implements ExceptionInterface
{
    public static function forOperation(string $operation): self
    {
        return new self(sprintf('Money %s overflows the integer range of cents', $operation));
    }
}
