<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Payment;

use Contenir\Commerce\Exception\ConfigurationException;
use Contenir\Commerce\Payment\Factory\StripeGatewayFactory;
use Contenir\Commerce\Payment\StripeGateway;
use Contenir\Commerce\Payment\UnconfiguredGateway;
use Contenir\Commerce\Tests\TestAsset\Clock\FixedClock;
use Contenir\Commerce\Tests\TestAsset\Container\ArrayContainer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[Group('unit')]
final class StripeGatewayFactoryTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidConfigProvider(): array
    {
        return [
            'stripe block not an array' => ['sk_test', 'Config "stripe" must be an array, got string'],
            'secret key not a string'   => [
                ['secret_key' => 123],
                'Config "stripe.secret_key" must be a string, got int',
            ],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unconfiguredProvider(): array
    {
        return [
            'no config service' => [[]],
            'config not array'  => [['config' => 'nope']],
            'no stripe block'   => [['config' => []]],
            'no secret key'     => [['config' => ['stripe' => []]]],
            'null secret key'   => [['config' => ['stripe' => ['secret_key' => null]]]],
            'empty secret key'  => [['config' => ['stripe' => ['secret_key' => '']]]],
        ];
    }

    #[Test]
    public function buildsTheStripeGatewayFromTheConfiguredSecretKey(): void
    {
        $container = new ArrayContainer([
            'config'              => ['stripe' => ['secret_key' => 'sk_test_fake']],
            ClockInterface::class => new FixedClock(new DateTimeImmutable('2026-08-20T10:00:00+10:00')),
        ]);

        static::assertInstanceOf(StripeGateway::class, (new StripeGatewayFactory())($container));
    }

    /**
     * @param array<string, mixed> $services
     */
    #[DataProvider('unconfiguredProvider')]
    #[Test]
    public function fallsBackToTheUnconfiguredGatewayWithoutASecretKey(array $services): void
    {
        static::assertInstanceOf(
            UnconfiguredGateway::class,
            (new StripeGatewayFactory())(new ArrayContainer($services)),
        );
    }

    #[DataProvider('invalidConfigProvider')]
    #[Test]
    public function rejectsMistypedConfiguration(mixed $stripe, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new StripeGatewayFactory())(new ArrayContainer(['config' => ['stripe' => $stripe]]));
    }
}
