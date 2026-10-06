<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Payment;

use Override;
use Stripe\HttpClient\ClientInterface;

use function array_shift;
use function is_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Offline stand-in for stripe-php's HTTP layer: no request ever reaches
 * the network. Queue responses before use and inspect captured requests
 * afterwards. With nothing queued it answers with a Stripe API error.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /**
     * @var list<array{method: string, url: string, headers: list<string>, params: array<array-key, mixed>}>
     */
    public array $requests = [];

    /**
     * @var list<array{body: array<string, mixed>, code: int}>
     */
    private array $responses = [];

    /**
     * @param array<string, mixed> $body
     */
    public function queueResponse(array $body, int $code = 200): void
    {
        $this->responses[] = ['body' => $body, 'code' => $code];
    }

    /**
     * @param mixed $method
     * @param mixed $absUrl
     * @param mixed $headers
     * @param mixed $params
     * @param mixed $hasFile
     * @param mixed $apiMode
     * @param mixed $maxNetworkRetries
     *
     * @return array{string, int, array<never, never>}
     *
     * @mago-expect lint:excessive-parameter-list The signature is stripe-php's ClientInterface::request().
     */
    #[Override]
    public function request(
        $method,
        $absUrl,
        $headers,
        $params,
        $hasFile,
        $apiMode = 'v1',
        $maxNetworkRetries = null,
    ): array {
        $captured = [];
        foreach (is_array($headers) ? $headers : [] as $header) {
            $captured[] = (string) $header;
        }

        $this->requests[] = [
            'method'  => (string) $method,
            'url'     => (string) $absUrl,
            'headers' => $captured,
            'params'  => is_array($params) ? $params : [],
        ];

        $response = array_shift($this->responses) ?? [
            'body' => ['error' => ['message' => 'No queued response', 'type' => 'api_error']],
            'code' => 500,
        ];

        return [json_encode($response['body'], flags: JSON_THROW_ON_ERROR), $response['code'], []];
    }
}
