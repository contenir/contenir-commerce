<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Model\Entity;

use Contenir\Commerce\Model\Entity\ArtistEnquiryEntity;
use Contenir\Commerce\Model\Entity\ArtistEnquiryFileEntity;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
use Contenir\Commerce\Model\Entity\EmailLogEntity;
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
            'artwork'             => [
                ArtworkEntity::class,
                'artwork',
                [
                    'artwork_id',
                    'resource_id',
                    'artist_resource_id',
                    'exhibition_resource_id',
                    'item_type',
                    'price',
                    'status',
                    'medium',
                    'dimensions',
                    'year',
                    'edition_details',
                    'external_sale_url',
                    'created',
                    'updated',
                ],
            ],
            'order'               => [
                OrderEntity::class,
                'gallery_order',
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
            'order item'          => [
                OrderItemEntity::class,
                'gallery_order_item',
                ['order_item_id', 'order_id', 'artwork_id', 'title', 'artist_name', 'price', 'created'],
            ],
            'artist enquiry'      => [
                ArtistEnquiryEntity::class,
                'artist_enquiry',
                [
                    'artist_enquiry_id',
                    'name',
                    'email',
                    'telephone',
                    'website',
                    'instagram',
                    'bio',
                    'statement',
                    'medium',
                    'preferred_timing',
                    'how_heard',
                    'status',
                    'staff_notes',
                    'created',
                    'updated',
                ],
            ],
            'artist enquiry file' => [
                ArtistEnquiryFileEntity::class,
                'artist_enquiry_file',
                ['artist_enquiry_file_id', 'artist_enquiry_id', 'filename', 'path', 'mime_type', 'size', 'created'],
            ],
            'email log'           => [
                EmailLogEntity::class,
                'email_log',
                [
                    'email_log_id',
                    'order_id',
                    'artist_enquiry_id',
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
            [['items'], ['files']],
            [
                array_keys($factory->getMetadataFor(OrderEntity::class)->relations),
                array_keys($factory->getMetadataFor(ArtistEnquiryEntity::class)->relations),
            ],
        );
    }
}
