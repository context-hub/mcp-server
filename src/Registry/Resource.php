<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Registry;

use PhpMcp\Schema\Annotations;

final class Resource extends \PhpMcp\Schema\Resource
{
    private const string URI_PATTERN = '/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/[^\s]*$/';

    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $mimeType = null,
        public readonly ?Annotations $annotations = null,
        public readonly ?int $size = null,
    ) {
        if (!preg_match(self::URI_PATTERN, $uri)) {
            throw new \InvalidArgumentException(
                "Invalid resource URI: must be a valid URI with a scheme and optional path.",
            );
        }
    }
}
