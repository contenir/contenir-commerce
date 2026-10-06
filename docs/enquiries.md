# Artist enquiries and the email log

## Enquiries

`Model\Entity\ArtistEnquiryEntity` maps `artist_enquiry`: a prospective artist's submission, reviewed by staff.
Like every entity, its columns live in an abstract base (`AbstractArtistEnquiryEntity`) that a site can extend with
columns of its own; see [entities](entities.md).

| Property | Column | Type |
| --- | --- | --- |
| `artistEnquiryId` | `artist_enquiry_id` | `?int`, generated |
| `name`, `email` | same | `string`, required |
| `telephone`, `website`, `instagram`, `bio`, `statement`, `medium` | same | `?string` |
| `preferredTiming` | `preferred_timing` | `?string` |
| `howHeard` | `how_heard` | `?string` |
| `status` | `status` | `EnquiryStatus`, default `NewEnquiry` (`new`) |
| `staffNotes` | `staff_notes` | `?string` |
| `created`, `updated` | same | `?DateTimeImmutable` |
| `files` | relation | `Collection<ArtistEnquiryFileEntity>`, in upload order |

`EnquiryStatus` has `new`, `under_review`, `shortlisted`, `declined` and `accepted`, each with a `label()`. Staff may
move an enquiry between any two states; there is no lifecycle to enforce.

```php
$enquiry        = $enquiries->newEntity();   // ArtistEnquiryRepository: the configured class
$enquiry->name  = 'June Hollis';
$enquiry->email = 'june@example.test';
$enquiry->created = $clock->now();
$em->save($enquiry);

$file                  = $enquiryFiles->newEntity();
$file->artistEnquiryId = $enquiry->artistEnquiryId;
$file->filename        = 'folio.jpg';
$file->path            = '/uploads/enquiry/folio.jpg';
$em->save($file);
```

`ArtistEnquiryFileEntity` maps `artist_enquiry_file`: `artistEnquiryFileId`, `artistEnquiryId`, `filename`, `path`,
`mimeType`, `size` and `created`. Validate uploads (type, size) before saving them; the entity does not.

| Repository | Finders |
| --- | --- |
| `ArtistEnquiryRepository` | `findByStatus(EnquiryStatus)`, newest first |
| `ArtistEnquiryFileRepository` | `findByArtistEnquiryId(int)`, in upload order |

## Email log

`Model\Entity\EmailLogEntity` maps `email_log`: one row per transactional email, linked to the order or the enquiry
it was about.

| Property | Column | Type |
| --- | --- | --- |
| `emailLogId` | `email_log_id` | `?int`, generated |
| `orderId` | `order_id` | `?int` |
| `artistEnquiryId` | `artist_enquiry_id` | `?int` |
| `recipient`, `subject` | same | `string`, required |
| `messageClass` | `message_class` | `?string` |
| `status` | `status` | `string`, the site's own vocabulary (`sent`, `failed`) |
| `error` | `error` | `?string` |
| `created` | `created` | `?DateTimeImmutable` |

`EmailLogRepository::findByOrderId(int)` and `findByArtistEnquiryId(int)` return the entries newest first.
