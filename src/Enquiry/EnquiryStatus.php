<?php

declare(strict_types=1);

namespace Contenir\Commerce\Enquiry;

/**
 * Staff pipeline for prospective artist submissions. Reclassification is
 * unrestricted: staff may move an enquiry between any two states.
 *
 * @api
 */
enum EnquiryStatus: string
{
    case NewEnquiry  = 'new';
    case UnderReview = 'under_review';
    case Shortlisted = 'shortlisted';
    case Declined    = 'declined';
    case Accepted    = 'accepted';

    public function label(): string
    {
        return match ($this) {
            self::NewEnquiry => 'New',
            self::UnderReview => 'Under review',
            self::Shortlisted => 'Shortlisted',
            self::Declined => 'Declined',
            self::Accepted => 'Accepted',
        };
    }
}
