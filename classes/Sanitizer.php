<?php

class Sanitizer {

    /**
     * Sanitiza uma string:
     * - remove tags HTML
     * - trim
     * - remove caracteres de controle
     * Retorna sempre string.
     *
     * @param mixed $input
     * @return string
     */
    public static function sanitizeString($input): string {
        $value = (string) $input;
        $value = strip_tags($value);
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value); // remove control chars
        return $value;
    }

    /**
     * Limpa um número inteiro.
     * @param mixed $input O valor a ser limpo.
     * @return int O número inteiro limpo.
     */
    public static function sanitizeInt($input) {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Limpa um número float.
     * @param mixed $input O valor a ser limpo.
     * @return float O número float limpo.
     */
    public static function sanitizeFloat($input) {
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Limpa um endereço de e-mail.
     * @param string $input O endereço de e-mail a ser limpo.
     * @return string O endereço de e-mail limpo ou uma string vazia se inválido.
     */
    public static function sanitizeEmail($input) {
        return filter_var($input, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Valida um endereço de e-mail.
     * @param string $input O endereço de e-mail a ser validado.
     * @return string|false O endereço de e-mail validado ou false se inválido.
     */
    public static function validateEmail($input) {
        return filter_var($input, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Limpa um URL.
     * @param string $input O URL a ser limpo.
     * @return string O URL limpo.
     */
    public static function sanitizeUrl($input) {
        return filter_var($input, FILTER_SANITIZE_URL);
    }

    /**
     * Valida um URL.
     * @param string $input O URL a ser validado.
     * @return string|false O URL validado ou false se inválido.
     */
    public static function validateUrl($input) {
        return filter_var($input, FILTER_VALIDATE_URL);
    }

    /**
     * Limpa um array de dados, aplicando sanitização a cada elemento string.
     * @param array $data O array de dados a ser limpo.
     * @return array O array com os dados limpos.
     */
    public static function sanitizeArray(array $data) {
        $sanitizedData = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitizedData[$key] = self::sanitizeArray($value);
            } else if (is_string($value) || is_scalar($value)) {
                $sanitizedData[$key] = self::sanitizeString($value);
            } else {
                $sanitizedData[$key] = $value;
            }
        }
        return $sanitizedData;
    }

    /**
     * Sanitiza um nome completo:
     * - remove espaços duplicados
     * - remove caracteres indesejados (mantém letras/acento, números, espaços, ' - .)
     * - normaliza para Title Case UTF-8 (com fallback se mbstring não disponível)
     *
     * @param string $name
     * @return string
     */
    public static function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        // Mantém letras (inclui acentos), marcas, números, espaços, apóstrofo, hífen e ponto
        $name = preg_replace('/[^\p{L}\p{Mn}0-9 \'\-\.]/u', '', $name);
        // Normaliza múltiplos pontos/hífens
        $name = preg_replace('/[\.]{2,}/u', '.', $name);
        $name = preg_replace('/[\-]{2,}/u', '-', $name);
        // Title Case: usa mb_convert_case quando disponível
        if (function_exists('mb_convert_case')) {
            $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        } else {
            $name = ucwords(strtolower($name));
        }
        return $name;
    }

    /**
     * Sanitiza telefone: mantém apenas dígitos
     */
    public static function sanitizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return $digits;
    }

    /**
     * Sanitiza CPF/CNPJ: mantém apenas dígitos e retorna vazio se não tiver 11 ou 14 dígitos
     */
    public static function sanitizeCpfCnpj(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) === 11 || strlen($digits) === 14) {
            return $digits;
        }
        return '';
    }

    /**
     * Compatibilidade: sanitiza CPF específico (retorna apenas 11 dígitos ou string vazia)
     * Mantido como wrapper para não alterar chamadas existentes no sistema.
     */
    public static function sanitizeCPF(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        return (strlen($digits) === 11) ? $digits : '';
    }

    /**
     * Sanitiza endereço (remove tags e normaliza espaços)
     */
    public static function sanitizeAddress(string $address): string
    {
        $address = strip_tags($address);
        $address = preg_replace('/\s+/u', ' ', trim($address));
        return $address;
    }
}