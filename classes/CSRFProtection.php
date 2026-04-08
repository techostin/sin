<?php
require_once __DIR__ . '/../config/config.php';

class CSRFProtection
{
    public static function generateToken()
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }

        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function getTokenField()
    {
        $token = self::generateToken();
        $name = htmlspecialchars(CSRF_TOKEN_NAME, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="' . $name . '" value="' . $value . '">';
    }

    public static function validatePostRequest()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        $postedToken = $_POST[CSRF_TOKEN_NAME] ?? '';
        $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';

        if (!is_string($postedToken) || !is_string($sessionToken)) {
            return false;
        }

        return $postedToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $postedToken);
    }
}

