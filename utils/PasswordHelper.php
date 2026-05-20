<?php
class PasswordHelper {
    /**
     * Generates a random alphanumeric temporary password.
     *
     * @param int $length The length of the password.
     * @return string The generated password.
     */
    public static function generateTemp(int $length = 12): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $count = strlen($chars);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $count - 1);
            $result .= substr($chars, $index, 1);
        }
        return $result;
    }
}
