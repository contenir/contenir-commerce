<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Order;

use Contenir\Commerce\Model\Repository\ArtworkRepository;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Model\Repository\OrderRepository;
use Contenir\Commerce\Order\ArtworkReservation;
use Contenir\Commerce\Order\CheckoutService;
use Contenir\Commerce\Order\CompletionService;
use Contenir\Commerce\Order\OrderStore;
use Contenir\Commerce\Order\PurchaseItemCheck;
use Contenir\Commerce\Order\Refunder;
use Contenir\Commerce\Payment\PaymentGatewayInterface;
use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Type\TypeRegistry;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Sqlite\AdapterPlatform;
use PhpDb\Sqlite\Pdo\Connection;
use PhpDb\Sqlite\Pdo\Driver;
use Psr\Clock\ClockInterface;

/**
 * One request's order services on its own database connection and its own
 * EntityManager, as a separate PHP process handling a webhook would have.
 */
final readonly class CommerceProcess
{
    public ArtworkRepository $artworks;

    public CheckoutService $checkout;

    public CompletionService $completion;

    public function __construct(string $dsn, PaymentGatewayInterface $gateway, ClockInterface $clock)
    {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $driver  = new Driver(connection: new Connection($pdo));
        $adapter = new Adapter($driver, new AdapterPlatform($driver));
        $em      = new EntityManager($adapter);

        $this->artworks   = new ArtworkRepository($em, $adapter, TypeRegistry::withDefaults());
        $store            = new OrderStore($em, new OrderRepository($em), new OrderItemRepository($em), $clock);
        $reservation      = new ArtworkReservation($this->artworks);
        $this->checkout   = new CheckoutService($store, $reservation, new PurchaseItemCheck(), $gateway);
        $this->completion = new CompletionService($store, $reservation, new Refunder($gateway), $gateway);
    }
}
