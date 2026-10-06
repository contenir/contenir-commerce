<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\TestAsset\Database;

/**
 * The commerce tables in SQLite form, with the columns the entities map.
 *
 * The two child tables carry an index on (parent id, a text column), as a
 * production schema might, so that an unordered lookup by parent returns
 * rows in index order rather than id order: the finders' explicit ordering
 * is then observable.
 */
final class Schema
{
    /**
     * @return list<string>
     */
    public static function create(): array
    {
        return [
            'CREATE TABLE artwork (
                artwork_id INTEGER PRIMARY KEY AUTOINCREMENT, resource_id INTEGER, artist_resource_id INTEGER,
                exhibition_resource_id INTEGER, item_type TEXT NOT NULL DEFAULT \'artwork\',
                price INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'available\', medium TEXT,
                dimensions TEXT, year TEXT, edition_details TEXT, external_sale_url TEXT, created TEXT, updated TEXT
            )',
            'CREATE TABLE gallery_order (
                order_id INTEGER PRIMARY KEY AUTOINCREMENT, order_ref TEXT NOT NULL, customer_name TEXT,
                customer_email TEXT, customer_phone TEXT, status TEXT NOT NULL DEFAULT \'pending\',
                total INTEGER NOT NULL DEFAULT 0, gst_amount INTEGER NOT NULL DEFAULT 0,
                stripe_checkout_session_id TEXT, stripe_payment_intent_id TEXT, customer_notes TEXT,
                staff_notes TEXT, paid_at TEXT, collected_at TEXT, refunded_at TEXT, cancelled_at TEXT,
                created TEXT, updated TEXT
            )',
            'CREATE TABLE gallery_order_item (
                order_item_id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, artwork_id INTEGER,
                title TEXT NOT NULL, artist_name TEXT, price INTEGER NOT NULL DEFAULT 0, created TEXT
            )',
            'CREATE INDEX gallery_order_item_order ON gallery_order_item (order_id, title)',
            'CREATE TABLE artist_enquiry (
                artist_enquiry_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL,
                telephone TEXT, website TEXT, instagram TEXT, bio TEXT, statement TEXT, medium TEXT,
                preferred_timing TEXT, how_heard TEXT, status TEXT NOT NULL DEFAULT \'new\', staff_notes TEXT,
                created TEXT, updated TEXT
            )',
            'CREATE TABLE artist_enquiry_file (
                artist_enquiry_file_id INTEGER PRIMARY KEY AUTOINCREMENT, artist_enquiry_id INTEGER NOT NULL,
                filename TEXT NOT NULL, path TEXT NOT NULL, mime_type TEXT, size INTEGER, created TEXT
            )',
            'CREATE INDEX artist_enquiry_file_enquiry ON artist_enquiry_file (artist_enquiry_id, filename)',
            'CREATE TABLE email_log (
                email_log_id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, artist_enquiry_id INTEGER,
                recipient TEXT NOT NULL, subject TEXT NOT NULL, message_class TEXT, status TEXT NOT NULL,
                error TEXT, created TEXT
            )',
        ];
    }
}
