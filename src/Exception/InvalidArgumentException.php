<?php

declare(strict_types=1);

namespace Contenir\Commerce\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

/**
 * A value passed to the package is not valid: a negative amount, an empty
 * order, a missing customer name, an out-of-range checkout expiry.
 *
 * @api
 */
final class InvalidArgumentException extends SplInvalidArgumentException implements ExceptionInterface {}
