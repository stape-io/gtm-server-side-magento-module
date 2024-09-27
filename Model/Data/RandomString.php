<?php

namespace Stape\Gtm\Model\Data;

class RandomString
{
    /**
     * Generate random string
     *
     * @param int $length
     * @return string
     */
    public function generate(int $length = 8)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = rand(1, $length); // Random length between 1 and 8
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        // Check if the string ends with 'kp' or 'gt'
        while (in_array(substr($randomString, -2), ['kp', 'gt'])) {
            $randomString = substr($randomString, 0, -2); // Remove last two characters
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString;
    }
}
