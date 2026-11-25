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

    /**
     * Retorna o token CSRF atual (gerando um se não existir).
     * @return string
     */
    public static function getToken()
    {
        return self::generateToken();
    }

    /**
     * Valida um token CSRF arbitrário contra a sessão.
     * Usado por APIs que recebem o token via header (X-CSRF-Token),
     * campo de formulário ou corpo JSON.
     *
     * @param string|null $token
     * @return bool
     */
    public static function validateToken($token)
    {
        $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';

        if (!is_string($token) || $token === '') {
            return false;
        }
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Lê o token CSRF de múltiplas fontes possíveis em uma requisição
     * (AJAX/fetch): header HTTP_X_CSRF_TOKEN, corpo JSON, $_POST ou $_GET.
     *
     * @return string|null
     */
    public static function tokenFromRequest()
    {
        $header = '';
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $header = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['X-CSRF-Token'])) {
                $header = $headers['X-CSRF-Token'];
            }
        }
        if (is_string($header) && $header !== '') {
            return $header;
        }

        if (isset($_POST[CSRF_TOKEN_NAME])) {
            return (string) $_POST[CSRF_TOKEN_NAME];
        }
        if (isset($_GET[CSRF_TOKEN_NAME])) {
            return (string) $_GET[CSRF_TOKEN_NAME];
        }

        // Corpo JSON
        $raw = file_get_contents('php://input');
        if ($raw !== '') {
            $dec = json_decode($raw, true);
            if (is_array($dec) && isset($dec[CSRF_TOKEN_NAME])) {
                return (string) $dec[CSRF_TOKEN_NAME];
            }
        }

        return null;
    }
}

