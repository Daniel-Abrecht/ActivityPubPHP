<?php

declare(strict_types = 1);
namespace dpa\crypto;

enum KeyType {
  case UNKNOWN;
  case RSA;
  case DSA;
  case DH;
  case EC;
  case SYMETRIC; // Special case, not an asymetric key

  public static function fromOpenSSL(int $c) : KeyType {
    switch($c){
      case OPENSSL_KEYTYPE_RSA: return KeyType::RSA;
      case OPENSSL_KEYTYPE_DSA: return KeyType::DSA;
      case OPENSSL_KEYTYPE_DH: return KeyType::DH;
      case OPENSSL_KEYTYPE_EC: return KeyType::EC;
    }
    return KeyType::UNKNOWN;
  }
};

abstract class Key {
  static public abstract function fromString(string $content) : ?static;
  public abstract function getType() : KeyType;
};

abstract class AsymetricKey extends Key {
  public \OpenSSLAsymmetricKey $key;
  public abstract function __construct(\OpenSSLAsymmetricKey $key);
  public function getType() : KeyType {
    $detail = openssl_pkey_get_details($this->key);
    return KeyType::fromOpenSSL($detail['type']??-1);
  }
  public static function getAllFromPEM(string $input) : array {
    preg_match_all("/^-----BEGIN .*-----\n(.|\n)*?\n-----END .*-----$/m", $input, $matches);
    $matches = $matches[0];
    $result = [];
    foreach($matches as $match){
      if(static::class == AsymetricKey::class){
        $key = PublicKey::fromString($match);
        if(!$key)
          $key = PrivateKey::fromString($match);
      }else{
        $key = static::fromString($match);
      }
      if($key)
        $result[] = $key;
    }
    return $result;
  }
};

function hashToOpenSSLAlgorithm(string $hash) : int|false {
  switch($hash){
    case 'sha1': return OPENSSL_ALGO_SHA1;
    case 'sha256': return OPENSSL_ALGO_SHA256;
    case 'sha512': return OPENSSL_ALGO_SHA512;
  }
  return false;
}

class PublicKey extends AsymetricKey {
  public function __construct(\OpenSSLAsymmetricKey $key){
    $this->key = $key;
  }
  static public function fromString(string $string) : ?static {
    $key = openssl_pkey_get_public($string);
    if(!$key)
      return null;
    return new static($key);
  }
  public function __toString() : string {
    $result = openssl_pkey_get_details($this->key);
    return $result ? ($result['key'] ?? '') : '';
  }
  public function verify(string $message, string $signature, string $hash) : bool {
    $algorithm = hashToOpenSSLAlgorithm($hash);
    if($algorithm === false){
      trigger_error("Can't handle hash: $hash");
      return false;
    }
    return openssl_verify($message, $signature, $this->key, $algorithm) === 1;
  }
};

class PrivateKey extends AsymetricKey {
  public function __construct(\OpenSSLAsymmetricKey $key){
    $this->key = $key;
  }
  static public function fromString(string $string) : ?static {
    $key = openssl_pkey_get_private($string);
    if(!$key)
      return null;
    return new static($key);
  }
  public function __toString() : string {
    $result = null;
    openssl_pkey_export($this->key, $result);
    return $result ?? '';
  }
};

interface __ISymetricKey {
  // This is a helper interface to ensure the constructor signature won't change inderived classes.
  public function __construct(string $key);
}

class SymetricKey extends Key implements __ISymetricKey {
  public string $key;
  public function __construct(string $key){
    $this->key = $key;
  }
  static public function fromString(string $string) : ?static {
    return $string ? new static($string) : null;
  }
  public function __toString() : string {
    return $this->key;
  }
  public function getType() : KeyType {
    return KeyType::SYMETRIC;
  }
}
