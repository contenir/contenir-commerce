<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Payment;

use Contenir\Commerce\Payment\CheckoutSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CheckoutSessionTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, bool, bool}>
     */
    public static function sessionProvider(): array
    {
        return [
            'open'                          => ['open', 'unpaid', false, false],
            'expired'                       => ['expired', 'unpaid', false, false],
            'complete and paid'             => ['complete', 'paid', true, true],
            'complete, funds still to come' => ['complete', 'unpaid', true, false],
            'complete, nothing to pay'      => ['complete', 'no_payment_required', true, false],
            'complete, status unknown'      => ['complete', null, true, false],
            'paid but not complete'         => ['open', 'paid', false, false],
        ];
    }

    #[DataProvider('sessionProvider')]
    #[Test]
    public function isPaidOnlyWhenCompleteAndThePaymentHasArrived(
        string $status,
        ?string $paymentStatus,
        bool $complete,
        bool $paid,
    ): void {
        $session = new CheckoutSession('cs_1', $status, paymentStatus: $paymentStatus);

        static::assertSame([$complete, $paid], [$session->isComplete(), $session->isPaid()]);
    }

    #[Test]
    public function optionalFieldsDefaultToNull(): void
    {
        $session = new CheckoutSession('cs_1', 'open');

        static::assertSame(
            [null, null, null, null],
            [$session->url, $session->paymentIntentId, $session->customerEmail, $session->paymentStatus],
        );
    }
}
