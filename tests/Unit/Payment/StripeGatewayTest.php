<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Payment;

use Contenir\Commerce\Exception\PaymentFailedException;
use Contenir\Commerce\Money\Money;
use Contenir\Commerce\Payment\CheckoutLineItem;
use Contenir\Commerce\Payment\CheckoutRequest;
use Contenir\Commerce\Payment\CheckoutSession;
use Contenir\Commerce\Payment\StripeGateway;
use Contenir\Commerce\Tests\TestAsset\Clock\FixedClock;
use Contenir\Commerce\Tests\TestAsset\Payment\FakeStripeHttpClient;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidArgumentException as StripeInvalidArgumentException;
use Stripe\Exception\InvalidRequestException;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;

use function array_filter;
use function array_values;
use function count;
use function str_starts_with;

/**
 * StripeGateway against stripe-php's real request and response handling,
 * with the HTTP layer replaced by FakeStripeHttpClient: nothing reaches the
 * network. The HTTP client is a stripe-php global, so tearDown() restores
 * the default.
 */
#[Group('unit')]
final class StripeGatewayTest extends TestCase
{
    private StripeGateway $gateway;

    private FakeStripeHttpClient $http;

    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function paymentIntentProvider(): array
    {
        return [
            'an id'              => ['pi_test_456', 'pi_test_456'],
            'an expanded intent' => [['id' => 'pi_test_789', 'object' => 'payment_intent'], 'pi_test_789'],
            'none'               => [null, null],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function settledSessionProvider(): array
    {
        return [
            'complete and paid' => [['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_1']],
            'complete, unpaid'  => [['status' => 'complete', 'payment_status' => 'unpaid', 'payment_intent' => 'pi_1']],
            'expired already'   => [['status' => 'expired']],
        ];
    }

    /**
     * @return array<string, array{callable(StripeGateway): mixed, string}>
     */
    public static function stripeErrorProvider(): array
    {
        return [
            'create'   => [
                static fn(StripeGateway $gateway): mixed => $gateway->createCheckoutSession(new CheckoutRequest(
                    [new CheckoutLineItem('Tote bag', Money::fromCents(3_500))],
                    'https://example.test/thanks',
                    'https://example.test/cart',
                )),
                'Unable to create Stripe checkout session',
            ],
            'retrieve' => [
                static fn(StripeGateway $gateway): mixed => $gateway->retrieveCheckoutSession('cs_missing'),
                'Unable to retrieve Stripe checkout session',
            ],
            'refund'   => [
                static fn(StripeGateway $gateway): mixed => $gateway->refund('pi_test_456'),
                'Unable to refund Stripe payment',
            ],
            'expire'   => [
                static fn(StripeGateway $gateway): mixed => $gateway->expireCheckoutSession('cs_test_123'),
                'Unable to expire Stripe checkout session',
            ],
        ];
    }

    #[Test]
    public function aFullRefundSendsNoAmountAndNoIdempotencyKey(): void
    {
        $this->http->queueResponse(['id' => 're_test_789', 'object' => 'refund', 'status' => 'succeeded']);

        $refund = $this->gateway->refund('pi_test_456');

        static::assertSame(
            [
                're_test_789',
                'succeeded',
                'post',
                'https://api.stripe.com/v1/refunds',
                ['payment_intent' => 'pi_test_456'],
                [],
            ],
            [
                $refund->id,
                $refund->status,
                $this->http->requests[0]['method'],
                $this->http->requests[0]['url'],
                $this->http->requests[0]['params'],
                $this->idempotencyHeaders(),
            ],
        );
    }

    #[Test]
    public function anIdempotencyKeyIsSentAsTheIdempotencyHeader(): void
    {
        $this->http->queueResponse(['id' => 're_test_789', 'object' => 'refund', 'status' => 'succeeded']);

        $this->gateway->refund('pi_test_456', idempotencyKey: 'refund-once');

        static::assertSame(['Idempotency-Key: refund-once'], $this->idempotencyHeaders());
    }

    #[Test]
    public function aPartialRefundSendsTheAmountInCents(): void
    {
        $this->http->queueResponse(['id' => 're_test_789', 'object' => 'refund', 'status' => 'pending']);

        $refund = $this->gateway->refund('pi_test_456', Money::fromCents(50_000));

        static::assertSame(
            ['pending', ['payment_intent' => 'pi_test_456', 'amount' => 50_000]],
            [$refund->status, $this->http->requests[0]['params']],
        );
    }

    #[Test]
    public function aRefundWithoutAStatusReportsAnEmptyStatus(): void
    {
        $this->http->queueResponse(['id' => 're_test_789', 'object' => 'refund', 'status' => null]);

        static::assertSame('', $this->gateway->refund('pi_test_456')->status);
    }

    #[Test]
    public function aRefusedExpiryOfASessionStillOpenFails(): void
    {
        $this->http->queueResponse($this->notOpenError(), 400);
        $this->queueSession(['status' => 'open']);

        try {
            $this->gateway->expireCheckoutSession('cs_test_123');
            static::fail('Expected PaymentFailedException');
        } catch (PaymentFailedException $e) {
            static::assertSame(
                ['Unable to expire Stripe checkout session', InvalidRequestException::class],
                [$e->getMessage(), $e->getPrevious()::class],
            );
        }
    }

    #[Test]
    public function aRefusedExpiryWhoseSessionCannotBeReadFailsOnTheRead(): void
    {
        $this->http->queueResponse($this->notOpenError(), 400);
        $this->http->queueResponse(['error' => [
            'message' => 'No such session',
            'type'    => 'invalid_request_error',
        ]], 404);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Unable to retrieve Stripe checkout session');

        $this->gateway->expireCheckoutSession('cs_test_123');
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('settledSessionProvider')]
    #[Test]
    public function aSessionThatCanNoLongerBeExpiredIsReturnedAsItIs(array $fields): void
    {
        $this->http->queueResponse($this->notOpenError(), 400);
        $this->queueSession($fields);

        $session = $this->gateway->expireCheckoutSession('cs_test_123');

        static::assertEquals(
            [
                new CheckoutSession(
                    'cs_test_123',
                    $fields['status'],
                    null,
                    $fields['payment_intent'] ?? null,
                    null,
                    $fields['payment_status'] ?? 'unpaid',
                ),
                'get',
                'https://api.stripe.com/v1/checkout/sessions/cs_test_123',
            ],
            [$session, $this->http->requests[1]['method'], $this->http->requests[1]['url']],
        );
    }

    #[Test]
    public function aSessionWithoutAStatusMapsToAnEmptyStatus(): void
    {
        $this->queueSession(['status' => null]);

        static::assertSame('', $this->gateway->retrieveCheckoutSession('cs_test_123')->status);
    }

    #[Test]
    public function chargesInTheConfiguredCurrencyInLowerCase(): void
    {
        $this->queueSession(['status' => 'open']);
        $gateway = new StripeGateway(
            new StripeClient('sk_test_fake'),
            new FixedClock(new DateTimeImmutable('2026-08-20T10:00:00+10:00')),
            'NZD',
        );

        $gateway->createCheckoutSession(new CheckoutRequest(
            [new CheckoutLineItem('Tote bag', Money::fromCents(3_500))],
            'https://example.test/thanks',
            'https://example.test/cart',
        ));

        static::assertSame('nzd', $this->http->requests[0]['params']['line_items'][0]['price_data']['currency']);
    }

    #[Test]
    public function createsAHostedCheckoutWithGstInclusiveAudLineItems(): void
    {
        $this->queueSession(['status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']);

        $session = $this->gateway->createCheckoutSession(new CheckoutRequest(
            [new CheckoutLineItem('Coastal Dawn', Money::fromCents(185_000), 2, 'Oil on canvas, 2026')],
            'https://example.test/thanks',
            'https://example.test/cart',
            'buyer@example.test',
            ['order_ref' => 'LR-2026-0001'],
            45,
        ));

        static::assertSame(
            [
                'cs_test_123',
                'open',
                'https://checkout.stripe.com/c/pay/cs_test_123',
                'post',
                'https://api.stripe.com/v1/checkout/sessions',
                [
                    'mode'           => 'payment',
                    'line_items'     => [
                        [
                            'quantity'   => 2,
                            'price_data' => [
                                'currency'     => 'aud',
                                'unit_amount'  => 185_000,
                                'product_data' => ['name' => 'Coastal Dawn', 'description' => 'Oil on canvas, 2026'],
                            ],
                        ],
                    ],
                    'success_url'    => 'https://example.test/thanks',
                    'cancel_url'     => 'https://example.test/cart',
                    'expires_at'     => (new DateTimeImmutable('2026-08-20T10:45:00+10:00'))->getTimestamp(),
                    'customer_email' => 'buyer@example.test',
                    'metadata'       => ['order_ref' => 'LR-2026-0001'],
                ],
            ],
            [
                $session->id,
                $session->status,
                $session->url,
                $this->http->requests[0]['method'],
                $this->http->requests[0]['url'],
                $this->http->requests[0]['params'],
            ],
        );
    }

    #[Test]
    public function expiresAnOpenSession(): void
    {
        $this->queueSession(['status' => 'expired']);

        $session = $this->gateway->expireCheckoutSession('cs_test_123');

        static::assertSame(
            ['expired', 'post', 'https://api.stripe.com/v1/checkout/sessions/cs_test_123/expire', 1],
            [
                $session->status,
                $this->http->requests[0]['method'],
                $this->http->requests[0]['url'],
                count($this->http->requests),
            ],
        );
    }

    #[Test]
    public function leavesOutTheEmailMetadataAndDescriptionWhenNotGiven(): void
    {
        $this->queueSession(['status' => 'open']);

        $this->gateway->createCheckoutSession(new CheckoutRequest(
            [new CheckoutLineItem('Tote bag', Money::fromCents(3_500))],
            'https://example.test/thanks',
            'https://example.test/cart',
        ));

        static::assertSame(
            [
                'mode'        => 'payment',
                'line_items'  => [
                    [
                        'quantity'   => 1,
                        'price_data' => [
                            'currency'     => 'aud',
                            'unit_amount'  => 3_500,
                            'product_data' => ['name' => 'Tote bag'],
                        ],
                    ],
                ],
                'success_url' => 'https://example.test/thanks',
                'cancel_url'  => 'https://example.test/cart',
                'expires_at'  => (new DateTimeImmutable('2026-08-20T10:30:00+10:00'))->getTimestamp(),
            ],
            $this->http->requests[0]['params'],
        );
    }

    #[DataProvider('paymentIntentProvider')]
    #[Test]
    public function mapsThePaymentIntentWhetherAnIdOrExpanded(mixed $paymentIntent, ?string $expected): void
    {
        $this->queueSession(['payment_intent' => $paymentIntent]);

        static::assertSame($expected, $this->gateway->retrieveCheckoutSession('cs_test_123')->paymentIntentId);
    }

    #[Test]
    public function retrievesACompletedPaidSession(): void
    {
        $this->queueSession([
            'status'         => 'complete',
            'payment_status' => 'paid',
            'url'            => null,
            'payment_intent' => 'pi_test_456',
            'customer_email' => 'buyer@example.test',
        ]);

        $session = $this->gateway->retrieveCheckoutSession('cs_test_123');

        static::assertEquals(
            [
                new CheckoutSession('cs_test_123', 'complete', null, 'pi_test_456', 'buyer@example.test', 'paid'),
                'get',
                'https://api.stripe.com/v1/checkout/sessions/cs_test_123',
            ],
            [$session, $this->http->requests[0]['method'], $this->http->requests[0]['url']],
        );
    }

    /**
     * @param callable(StripeGateway): mixed $operation
     */
    #[DataProvider('stripeErrorProvider')]
    #[Test]
    public function stripeApiErrorsBecomePaymentFailures(callable $operation, string $message): void
    {
        $this->http->queueResponse(['error' => [
            'message' => 'Invalid API key',
            'type'    => 'invalid_request_error',
        ]], 401);

        try {
            $operation($this->gateway);
            static::fail('Expected PaymentFailedException');
        } catch (PaymentFailedException $e) {
            static::assertSame(
                [$message, true],
                [$e->getMessage(), $e->getPrevious() instanceof ApiErrorException],
            );
        }
    }

    #[Test]
    public function stripeArgumentErrorsArePaymentFailuresToo(): void
    {
        try {
            $this->gateway->retrieveCheckoutSession(' ');
            static::fail('Expected PaymentFailedException');
        } catch (PaymentFailedException $e) {
            static::assertSame(
                ['Unable to retrieve Stripe checkout session', StripeInvalidArgumentException::class, []],
                [$e->getMessage(), $e->getPrevious()::class, $this->http->requests],
            );
        }
    }

    #[Override]
    protected function setUp(): void
    {
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);

        $this->gateway = new StripeGateway(
            new StripeClient('sk_test_fake'),
            new FixedClock(new DateTimeImmutable('2026-08-20T10:00:00+10:00')),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }

    /**
     * @return list<string>
     */
    private function idempotencyHeaders(): array
    {
        return array_values(array_filter(
            $this->http->requests[0]['headers'],
            static fn(string $header): bool => str_starts_with($header, 'Idempotency-Key:'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function notOpenError(): array
    {
        return ['error' => [
            'message' => 'Only Checkout Sessions with a status in ["open"] can be expired.',
            'type'    => 'invalid_request_error',
        ]];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function queueSession(array $fields): void
    {
        $this->http->queueResponse([
            'id'             => 'cs_test_123',
            'object'         => 'checkout.session',
            'status'         => 'open',
            'payment_status' => 'unpaid',
            'url'            => null,
            'payment_intent' => null,
            'customer_email' => null,
            ...$fields,
        ]);
    }
}
