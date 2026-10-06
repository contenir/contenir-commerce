<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use RuntimeException;

use function implode;
use function sprintf;

/**
 * One or more works in an order have been sold, withdrawn or deleted since
 * they were carted.
 *
 * @api
 */
final class ArtworkUnavailableException extends RuntimeException implements ExceptionInterface
{
    /**
     * @param list<string> $titles
     */
    private function __construct(
        string $message,
        private readonly array $titles,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $titles
     */
    public static function forTitles(array $titles): self
    {
        return new self(sprintf('No longer available: %s', implode(', ', $titles)), $titles);
    }

    /**
     * @return list<string>
     */
    public function getTitles(): array
    {
        return $this->titles;
    }
}
