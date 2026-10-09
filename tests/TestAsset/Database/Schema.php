<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Database;

/**
 * The commerce tables in SQLite form, with the columns the entities map.
 * Four extra columns (item.medium, item_variant.frame,
 * commerce_order.gift_message and commerce_order_item.edition_note) stand
 * for a site's own columns; only the site entities in TestAsset\Entity map
 * them.
 *
 * The item table and the two child tables carry an index on (a filter
 * column, a text column), as a production schema might, so that an
 * unordered lookup returns rows in index order rather than id order: the
 * finders' explicit ordering is then observable.
 */
final class Schema
{
    /**
     * @return list<string>
     */
    public static function create(): array
    {
        return [
            'CREATE TABLE item (
                item_id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, description TEXT,
                status TEXT NOT NULL DEFAULT \'listed\', created TEXT, updated TEXT, medium TEXT
            )',
            'CREATE INDEX item_status ON item (status, title)',
            'CREATE TABLE item_variant (
                item_variant_id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, label TEXT, sku TEXT,
                price INTEGER NOT NULL DEFAULT 0, stock INTEGER, sequence INTEGER NOT NULL DEFAULT 0,
                created TEXT, updated TEXT, frame TEXT
            )',
            'CREATE INDEX item_variant_item ON item_variant (item_id, label)',
            'CREATE TABLE commerce_order (
                order_id INTEGER PRIMARY KEY AUTOINCREMENT, order_ref TEXT NOT NULL, customer_name TEXT,
                customer_email TEXT, customer_phone TEXT, status TEXT NOT NULL DEFAULT \'pending\',
                total INTEGER NOT NULL DEFAULT 0, gst_amount INTEGER NOT NULL DEFAULT 0,
                stripe_checkout_session_id TEXT, stripe_payment_intent_id TEXT, customer_notes TEXT,
                staff_notes TEXT, paid_at TEXT, collected_at TEXT, refunded_at TEXT, cancelled_at TEXT,
                created TEXT, updated TEXT, gift_message TEXT
            )',
            'CREATE TABLE commerce_order_item (
                order_item_id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, item_id INTEGER,
                item_variant_id INTEGER, title TEXT NOT NULL, variant_label TEXT, description TEXT,
                unit_price INTEGER NOT NULL DEFAULT 0, quantity INTEGER NOT NULL DEFAULT 1, created TEXT,
                edition_note TEXT
            )',
            'CREATE INDEX commerce_order_item_order ON commerce_order_item (order_id, title)',
            'CREATE TABLE email_log (
                email_log_id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, recipient TEXT NOT NULL,
                subject TEXT NOT NULL, message_class TEXT, status TEXT NOT NULL, error TEXT, created TEXT
            )',
        ];
    }
}
