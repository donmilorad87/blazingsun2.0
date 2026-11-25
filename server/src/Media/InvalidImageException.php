<?php

declare(strict_types=1);

namespace BlazingSun\Api\Media;

use RuntimeException;

/** The uploaded file is not an image we accept; the message is shown to the admin. */
final class InvalidImageException extends RuntimeException
{
}
