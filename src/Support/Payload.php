<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Support;

use IntrAgile\NavOnlineSzamla\Exceptions\NavException;

/**
 * Base64 and GZIP handling of embedded invoice documents.
 */
final class Payload
{
    /**
     * NAV rejects invoices larger than 15 MB uncompressed; anything bigger is refused on
     * decode as well, which also guards against decompression bombs.
     */
    public const MAX_UNCOMPRESSED_BYTES = 15 * 1024 * 1024;

    /**
     * Request bodies larger than 10 MB are rejected by NAV.
     */
    public const MAX_REQUEST_BYTES = 10 * 1024 * 1024;

    public static function encode(string $xml, bool $compress): string
    {
        if (strlen($xml) > self::MAX_UNCOMPRESSED_BYTES) {
            throw new NavException('The invoice XML exceeds the 15 MB limit accepted by NAV.');
        }

        if (! $compress) {
            return base64_encode($xml);
        }

        // NAV requires compression level 1; higher levels are moved to the end of the queue.
        $compressed = gzencode($xml, 1);
        if ($compressed === false) {
            throw new NavException('The invoice XML could not be compressed.');
        }

        return base64_encode($compressed);
    }

    public static function decode(string $base64, bool $compressed): string
    {
        $data = base64_decode(trim($base64), true);
        if ($data === false) {
            throw new NavException('NAV returned an invalid base64 payload.');
        }

        if (! $compressed) {
            return $data;
        }

        $xml = @gzdecode($data, self::MAX_UNCOMPRESSED_BYTES);
        if ($xml === false) {
            throw new NavException('NAV returned a payload that cannot be decompressed or exceeds 15 MB.');
        }

        return $xml;
    }
}
