<?php

namespace App\Traits;

use Exception;
use RuntimeException;

trait GeneratePassword
{
    /**
     * @throws Exception
     */
    public function generatePassword($length)
    {
        return substr(preg_replace("/[A-Za-z0-9_@.\/&#$%()+-]*$/", "", base64_encode($this->getRandomBytes($length + 1))), 0, $length);
    }

    /**
     * @throws Exception
     */
    public function getRandomBytes($nbBytes = 32): string
    {
        $bytes = random_bytes($nbBytes);
        if (false !== $bytes) {
            return $bytes;
        }

        throw new RuntimeException("Unable to generate secure token from OpenSSL.");

    }

}
