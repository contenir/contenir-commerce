<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Model\Entity;

use Contenir\Commerce\Model\Entity\EmailLogEntity;
use Contenir\Commerce\Model\Entity\ItemEntity;
use Contenir\Commerce\Model\Entity\ItemVariantEntity;
use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Db\Model\Metadata\AttributeMetadataFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[Group('unit')]
final class EntityMappingTest extends TestCase
{
    /**
     * @return array<string, array{class-string, string, list<string>}>
     */
    public static function entityProvider(): array
    {
        return [
            'item'         => [
                ItemEntity::class,
                'item',
                ['item_id', 'title', 'description', 'status', 'created', 'updated'],
            ],
            'item variant' => [
                ItemVariantEntity::class,
                'item_variant',
                [
                    'item_variant_id',
                    'item_id',
                    'label',
                    'sku',
                    'price',
                    'stock',
                    'sequence',
                    'created',
                    'updated',
                ],
            ],
            'order'        => [
                OrderEntity::class,
                'commerce_order',
                [
                    'order_id',
                    'order_ref',
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'status',
                    'total',
                    'gst_amount',
                    'stripe_checkout_session_id',
                    'stripe_payment_intent_id',
                    'customer_notes',
                    'staff_notes',
                    'paid_at',
                    'collected_at',
                    'refunded_at',
                    'cancelled_at',
                    'created',
                    'updated',
                ],
            ],
            'order item'   => [
                OrderItemEntity::class,
                'commerce_order_item',
                [
                    'order_item_id',
                    'order_id',
                    'item_id',
                    'item_variant_id',
                    'title',
                    'variant_label',
                    'description',
                    'unit_price',
                    'quantity',
                    'created',
                ],
            ],
            'email log'    => [
                EmailLogEntity::class,
                'email_log',
                [
                    'email_log_id',
                    'order_id',
                    'recipient',
                    'subject',
                    'message_class',
                    'status',
                    'error',
                    'created',
                ],
            ],
        ];
    }

    /**
     * @param class-string $entityClass
     * @param list<string> $columns
     */
    #[DataProvider('entityProvider')]
    #[Test]
    public function mapsEveryColumnOfItsTable(string $entityClass, string $table, array $columns): void
    {
        $metadata = (new AttributeMetadataFactory())->getMetadataFor($entityClass);

        static::assertSame(
            [$table, $columns],
            [$metadata->table, $metadata->getColumnNames()],
        );
    }

    #[Test]
    public function theToManyRelationsAreDeclared(): void
    {
        $factory = new AttributeMetadataFactory();

        static::assertSame(
            [['items'], ['variants']],
            [
                array_keys($factory->getMetadataFor(OrderEntity::class)->relations),
                array_keys($factory->getMetadataFor(ItemEntity::class)->relations),
            ],
        );
    }
}
