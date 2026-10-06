<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Order\CustomerDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CustomerDetailsTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function missingFieldProvider(): array
    {
        return [
            'no name'  => ['', 'avery@example.test'],
            'no email' => ['Avery Buyer', ''],
        ];
    }

    #[Test]
    public function phoneAndNotesAreOptional(): void
    {
        $customer = new CustomerDetails('Avery Buyer', 'avery@example.test');

        static::assertSame(
            ['Avery Buyer', 'avery@example.test', null, null],
            [$customer->name, $customer->email, $customer->phone, $customer->notes],
        );
    }

    #[DataProvider('missingFieldProvider')]
    #[Test]
    public function requiresANameAndEmail(string $name, string $email): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer name and email are required');

        new CustomerDetails($name, $email);
    }
}
