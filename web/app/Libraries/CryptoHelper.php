<?php

namespace App\Libraries;

class CryptoHelper
{
    private string $cipherAlgo = "AES-128-CBC";
    private string $cryptKey;
    private string $cryptIv;

    public function __construct()
    {
        $rawKey = (string) env('MPESA_CRYPT_KEY', 'a:r2yt>N3_\\Py,f=');
        $rawIv  = (string) env('MPESA_CRYPT_IV', '[M[@_w[F4a>yQsJW');
        $this->cryptKey = trim($rawKey, "'\"");
        $this->cryptIv  = trim($rawIv, "'\"");
    }

    public function decode_content($value)
    {
        if (strlen($value) < 16) {
            throw new \RuntimeException("Payload too short to extract IV");
        }

        $options = OPENSSL_RAW_DATA;

        /**
         * Candidate key list.
         * Why: In Android Kotlin/Java source code, the hardcoded fallback encryption key literal
         * contains a backslash: "a:r2yt>N3_\\Py,f=". In Java/Kotlin string parsing, "\\\\"
         * represents a single backslash character '\'. However, depending on whether .env / docker-compose
         * environment variables are quoted with single or double quotes, PHP may receive either:
         * 1) 'a:r2yt>N3_\Py,f=' (single backslash, length 16 bytes)
         * 2) 'a:r2yt>N3_\\Py,f=' (literal double backslash, length 17 bytes)
         * Both variations and hex-decoded variants are tested in order to preserve zero-downtime
         * compatibility with all Android client build variants.
         */
        $candidates = [
            $this->cryptKey,
            'a:r2yt>N3_\\Py,f=',
            'a:r2yt>N3_\Py,f='
        ];
        if (ctype_xdigit($this->cryptKey) && strlen($this->cryptKey) === 32) {
            $candidates[] = hex2bin($this->cryptKey);
        }
        $candidates = array_unique(array_filter($candidates));

        // 1. Attempt AES-256-GCM decryption (12-byte IV header + 16-byte Auth Tag footer)
        if (strlen($value) > 28) {
            $ivGcm = substr($value, 0, 12);
            $tag = substr($value, -16);
            $ciphertextGcm = substr($value, 12, -16);

            foreach ($candidates as $key) {
                $gcmDec = @openssl_decrypt($ciphertextGcm, 'AES-256-GCM', $key, $options, $ivGcm, $tag);
                if ($gcmDec !== false && $gcmDec !== '') {
                    log_message('debug', 'Decrypted payload using AES-256-GCM');
                    return $gcmDec;
                }
            }
        }

        // 2. Dynamic IV AES-128-CBC decryption (16-byte IV header + ciphertext)
        $iv = substr($value, 0, 16);
        $ciphertext = substr($value, 16);

        foreach ($candidates as $key) {
            $dec_val = @openssl_decrypt($ciphertext, $this->cipherAlgo, $key, $options, $iv);
            if ($dec_val !== false && $dec_val !== '') {
                log_message('debug', 'Decrypted payload using AES-128-CBC dynamic IV');
                return $dec_val;
            }
        }

        // 3. Fallback to fixed IV AES-128-CBC (legacy payloads)
        if (!empty($this->cryptIv)) {
            $fixedIv = $this->cryptIv;
            foreach ($candidates as $key) {
                $dec_val = @openssl_decrypt($value, $this->cipherAlgo, $key, $options, $fixedIv);
                if ($dec_val !== false && $dec_val !== '') {
                    log_message('debug', 'Decrypted payload using AES-128-CBC fixed IV');
                    return $dec_val;
                }
            }
        }

        $error = openssl_error_string();
        log_message('error', 'OpenSSL decrypt error: ' . $error);
        throw new \RuntimeException("OpenSSL decrypt failed: " . ($error ?: 'Invalid key or corrupted payload'));
    }

    public function encode_content($value)
    {
        $iv = openssl_random_pseudo_bytes(12);
        $options = OPENSSL_RAW_DATA;
        $tag = '';

        $enc_val = openssl_encrypt($value, 'AES-256-GCM', $this->cryptKey, $options, $iv, $tag);

        return $iv . $enc_val . $tag;
    }
}
