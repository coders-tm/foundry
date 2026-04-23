<?php

namespace Foundry\Traits;

trait JsonCompressible
{
    /**
     * Decompress and decode JSON data.
     *
     * @return mixed
     */
    protected function uncompress(string $value)
    {
        try {
            return json_decode(gzuncompress($value), true);
        } catch (\Throwable $e) {
            // If the data is not compressed, directly decode it
            return json_decode($value, true);
        }
    }
}
