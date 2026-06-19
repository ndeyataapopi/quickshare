<?php

namespace App\Traits;

use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Support\Facades\Crypt;

trait Encryptable
{
    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);

        if (in_array($key, $this->encryptable) && ! is_null($value)) {
            try {
                return Crypt::decrypt($value);
            } catch (EncryptException $e) {
                return $value; // Return as-is if decryption fails
            }
        }

        return $value;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encryptable) && ! is_null($value)) {
            $value = Crypt::encrypt($value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getEncryptableFields(): array
    {
        return $this->encryptable;
    }
}
