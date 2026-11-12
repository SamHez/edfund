<?php
declare(strict_types=1);
namespace EdFund;
final class Crypto
{
    public function __construct(private readonly string $key) {}
    public function seal(string $plaintext): string
    {
        $nonce = random_bytes(12); $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag, 'edfund-mfa-v1');
        if ($ciphertext === false) { throw new \RuntimeException('Encryption failed.'); }
        return 'v1:'.base64_encode($nonce.$tag.$ciphertext);
    }
    public function open(string $sealed): string
    {
        if (!str_starts_with($sealed, 'v1:')) { throw new \RuntimeException('Invalid encrypted value.'); }
        $raw = base64_decode(substr($sealed,3), true);
        if ($raw === false || strlen($raw) < 29) { throw new \RuntimeException('Invalid encrypted value.'); }
        $plain = openssl_decrypt(substr($raw,28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw,0,12), substr($raw,12,16), 'edfund-mfa-v1');
        if ($plain === false) { throw new \RuntimeException('Decryption failed.'); }
        return $plain;
    }
    public function digest(string $value): string { return hash_hmac('sha256', $value, $this->key); }
}
