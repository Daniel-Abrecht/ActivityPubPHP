<?php

declare(strict_types = 1);
namespace dpa\http;

enum VerificationResult {
  case INVALID;
  case VALID;
  case NO_SIGNATURE;
  case VALID_BUT_INSECURE;

  public static function any(VerificationResult... $vrs) : VerificationResult {
    foreach($vrs as $vr)
      if($vr == static::INVALID)
        return static::INVALID;
    foreach($vrs as $vr)
      if($vr == static::VALID)
        return static::VALID;
    foreach($vrs as $vr)
      if($vr == static::VALID_BUT_INSECURE)
        return static::VALID_BUT_INSECURE;
    return static::NO_SIGNATURE;
  }

  public static function all(VerificationResult... $vrs) : VerificationResult {
    if(!$vrs)
      return static::NO_SIGNATURE;
    foreach($vrs as $vr)
      if($vr == static::INVALID)
        return static::INVALID;
    foreach($vrs as $vr)
      if($vr == static::NO_SIGNATURE)
        return static::NO_SIGNATURE;
    foreach($vrs as $vr)
      if($vr == static::VALID_BUT_INSECURE)
        return static::VALID_BUT_INSECURE;
    return static::VALID;
  }
};

class SignatureAlgorithm {
  public static array $map = [];
  public function __construct(
    public string $name,
    public string $hash_algorithm,
    public array $signature_algorithm,
    public ?\DateTimeImmutable $deprecated
  ){}
  public function getSignatureAlgorithmsForKeyType(KeyType $kt) : array {
    $results = [];
    foreach($this->signature_algorithm as $sa){
      if(str_starts_with($sa, 'RSA') && $kt == KeyType::RSA){
        $results[] = $sa;
      }else if($sa == 'HMAC' && $kt == KeyType::SYMETRIC){
        $results[] = $sa;
      }else if($sa == 'ECDSA' && $kt == KeyType::RSA){
        $results[] = $sa;
      }else if(str_starts_with($sa, 'ED') && $kt == KeyType::DSA){
        $results[] = $sa;
      }
    }
    return $results;
  }
  public static function get(?string $algorithm) : ?SignatureAlgorithm {
    if(!$algorithm) $algorithm = 'hs2019';
    return @self::$map[$algorithm];
  }
  public static function add(SignatureAlgorithm... $sas) : void {
    foreach($sas as $sa)
      self::$map[$sa->name] = $sa;
  }
  public function verify(PublicKey|SymetricKey $key, string $message, string $signature) : bool {
    if(!$this->getSignatureAlgorithmsForKeyType($key->getType())){
      trigger_error("Key not for any of the allowed signature algorithms, refusing to do verification");
      return false;
    }
    if($key instanceof PublicKey)
      return $key->verify($message, $signature, $this->hash_algorithm);
    trigger_error("Dealing with symetric keys not yet implemented", E_USER_WARNING);
    return false;
  }
};

SignatureAlgorithm::add(
  new SignatureAlgorithm(
    'hs2019',
    'sha512',
    ['RSASSA-PSS', 'HMAC', 'ECDSA', 'ED25519PH', 'ED25519CTX', 'ED25519'],
    null
  ),
  new SignatureAlgorithm(
    'rsa-sha1',
    'sha1',
    ['RSASSA-PKCS1-v1_5'],
    new \DateTimeImmutable('2014-05-08') // https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures-02#appendix-E.2
  ),
  new SignatureAlgorithm(
    'rsa-sha256',
    'sha256',
    ['RSASSA-PKCS1-v1_5'],
    new \DateTimeImmutable('2018-05-14') // https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures-11#appendix-E.2
  ),
  new SignatureAlgorithm(
    'hmac-sha256',
    'sha256',
    ['HMAC'],
    new \DateTimeImmutable('2018-05-14') // https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures-11#appendix-E.2
  ),
  new SignatureAlgorithm(
    'ecdsa-sha256',
    'sha256',
    ['ECDSA'],
    new \DateTimeImmutable('2018-05-14') // https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures-11#appendix-E.2
  )
);

class HTTPDoc {
  private static ?HTTPDoc $br = null;
  public ?HTTPSignature $signature = null;
  public string $method;
  public string $location;
  public string $message;
  public array $headers = [];
  public function __construct(
    string $method,
    string $location,
    ?string $message = null,
    array $headers = []
  ){
    $this->method = strtolower($method);
    $this->location = $location;
    $this->message = $message ?? '';
    $this->headers = array_change_key_case($headers, CASE_LOWER);
    $this->signature = HTTPSignature::fromHTTPDoc($this);
    $this->trim();
  }
  // Remove unnecessary headers & stuff
  private function trim() : void {
    $headers = [
      'host' => 1,
      'date' => 1,
      'referer' => 1,
      'origin' => 1,
      'signature' => 1
    ];
    if($this->signature){
      foreach($this->signature->headers as $h)
        $headers[$h] = 1;
      if($this->signature->is_auth){
        $headers['authorization'] = 1;
      }else{
        unset($headers['authorization']);
      }
    }
    $this->headers = array_filter(
      $this->headers,
      function($key) use ($headers) {
        return @$headers[$key];
      }, ARRAY_FILTER_USE_KEY
    );
  }
  public static function fromCurrentRequest() : HTTPDoc {
    if(self::$br)
      return self::$br;
    $method = $_SERVER['REQUEST_METHOD'];
    $location = $_SERVER['REQUEST_URI'];
    $message = file_get_contents('php://input');
    self::$br = new HTTPDoc($method, $location, $message?$message:null, getallheaders());
    return self::$br;
  }
  public function verify(
    ?\DateTimeInterface $received_time=null,
    bool $received_time_trusted=false,
    bool $check_message_body=true
  ) : VerificationResult {
    if(!$this->signature)
      return VerificationResult::NO_SIGNATURE;
    return $this->signature->verify($received_time, $received_time_trusted, $check_message_body);
  }
  public function checkDigest(?string $header=null, ?\DateTimeInterface $trusted_verification_date=null) : VerificationResult {
    if(!$header){
      $ret = [];
      foreach(['digest','repr-digest'] as $header)
        if(isset($this->headers[$header]))
          $ret[] = $this->checkDigest($header);
      return VerificationResult::all(...$ret);
    }
    $hashes = [
      "SHA-512" => [512,"sha512",null],
      "SHA-384" => [384,"sha384",null],
      "SHA-256" => [256,"sha256",null],
      "SHA-1"   => [160,"sha1",new \DateTimeImmutable('2014-05-08')],
    ];
    $header = strtolower($header);
    $digest = $this->headers[$header];
    if(!$digest)
      return VerificationResult::NO_SIGNATURE;
    $insecure = true;
    $has_signature = false;
    foreach(explode(',', $digest) as $hash){
      $a = explode('=', $digest, 2);
      $key = trim($a[0]);
      $value = $a[1]??null;
      if($value === null){
        $value = $key;
        $key = null;
        $len = strlen($value);
        foreach($hashes as $hn=>$hash){
          if( $hash[0]/8*2 == $len // HEX
           || (($hash[0]/8+2)/3|0)*4 == $len // base64
          ){
            $key = $hn;
            break;
          }
        }
        if(!$key)
          continue;
      }
      $value = trim($value, ": \n\r\t\v\x00");
      $len = strlen($value);
      $hash = @$hashes[strtoupper($key)];
      if($len == $hash[0]/8*2){
      }else if($len == (($hash[0]/8+2)/3|0)*4){
        $value = @bin2hex(@base64_decode($value));
      }else{
        return VerificationResult::INVALID;
      }
      $result = hash($hash[1], $this->message);
      if(!hash_equals($value, $result)) // Note:
        return VerificationResult::INVALID;
      if($hash[2] === null || ($trusted_verification_date && $trusted_verification_date <= $hash[2]))
        $insecure = false;
      $has_signature = true;
    }
    if(!$has_signature)
      return VerificationResult::NO_SIGNATURE;
    return $insecure ? VerificationResult::VALID_BUT_INSECURE : VerificationResult::VALID;
  }
};

function parse_http_signature(?string $sigstr) : ?array {
  if(!$sigstr)
    return null;
  if(str_starts_with($sigstr, 'Signature '))
    $sigstr = trim(explode(' ', $sigstr, 2)[1]);
  if(!preg_match_all('/[^=,]+(=("([^"\\\\]|\\\\.)+"|[^,]*))?/', $sigstr, $matches, PREG_PATTERN_ORDER))
    return null;
  $matches = $matches[0];
  $dict = [];
  foreach($matches as $str){
    $a = explode('=', $str, 2);
    $key = $a[0];
    $value = $a[1]??'';
    $key = trim($key);
    $key = trim($key);
    if($value){
      if($value[0] == '"'){
        $value = json_decode($value);
        if(!$value)
          return null;
      }else if(ctype_digit($value)){
        $value = +$value;
      }
      if(isset($dict[$key]))
        return null;
    }
    $dict[$key] = $value;
  }
  return $dict;
}

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
  protected static function load_key_string(string $uri) : ?string {
    if($uri === 'Test'){
      $uri = \dpa\BASE . '/test/test.pem';
    }else if(!str_starts_with($uri, 'https://')){
      trigger_error("Will not handle key from: $uri", E_USER_WARNING);
      return null;
    }
    $ret = file_get_contents($uri);
    return $ret === false ? null : $ret;
  }
  static public function load(string $uri) : ?static {
    $key = static::load_key_string($uri);
    if(!$key)
      return null;
    return static::fromString($key);
  }
  static public abstract function fromString(string $uri) : ?static;
  public abstract function getType() : KeyType;
};

abstract class AsymetricKey extends Key {
  public \OpenSSLAsymmetricKey $key;
  public abstract function __construct(\OpenSSLAsymmetricKey $key);
  public function getType() : KeyType {
    $detail = openssl_pkey_get_details($this->key);
    return KeyType::fromOpenSSL($detail['type']??-1);
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

/**
 * Notes
 * If the signature is valid, don't forget to check if the expected actor/authority signed it!
 * Also, if this is used for things like authentication, check if "expires" is set, to make sure it's not going to be reusable later.
 */
class HTTPSignature {
  private function __construct(
    public readonly bool $is_auth,
    public readonly string $keyId,
    public readonly string $signature,
    public readonly ?string $algorithm, // Note: Do not trust this value
    public readonly ?int $created,
    public readonly ?int $expires,
    public readonly array $headers,
    public readonly HTTPDoc $doc,
    public readonly int $skew_seconds // How much off the clock is allowed to bes
  ){}

  public static function fromHTTPDoc(HTTPDoc $doc) : ?HTTPSignature {
    $sig = new class() {
      public bool $is_auth = false;
      public string $keyId;
      public string $signature;
      public ?string $algorithm = null; // Note: Do not trust this value
      public ?int $created = null;
      public ?int $expires = null;
      public array $headers = [];
      public HTTPDoc $doc;
      public int $skew_seconds = 60; // How much off the clock is allowed to bes
    };

    $sig->doc = $doc;
    if(!($sigstr = @$doc->headers['signature'])){
      if(str_starts_with(@$doc->headers['authorization']??'','Signature '))
        if($sigstr = @$doc->headers['authorization'])
          $is_auth = true;
    }
    if(!$sigstr)
      return null;
    if(!($dict = parse_http_signature($sigstr)))
      return null;

    try {
      $sig->keyId = @$dict['keyId'];
      $sig->signature = @base64_decode(@$dict['signature']);
      $sig->algorithm = strtolower(@$dict['algorithm']);
      $created = @$dict['created'];
      if(!$created && isset($doc->headers['date']))
        $created = strtotime($doc->headers['date']);
      $sig->created = $created;
      $sig->expires = @$dict['expires'];
      if(@$dict['headers']){
        $sig->headers = array_map('strtolower', explode(' ', $dict['headers']));
      }else{
        if(in_array($sig->algorithm, ['rsa-sha1','rsa-sha256','hmac-sha256','ecdsa-sha256'])){
          $sig->headers = ['date'];
        }else{
          $sig->headers = ['(created)'];
        }
      }
    } catch(\Exception|\Throwable $e) {
      return null;
    }

    // Not required by the spec, but if these are not signed, we can't trust them, so ignore them instead.
    if(!in_array('(created)', $sig->headers))
      $sig->created = null;
    if(!in_array('(expires)', $sig->headers))
      $sig->expires = null;

    if(!$sig->keyId || !$sig->signature)
      return null;

    return new HTTPSignature(
      is_auth: $sig->is_auth,
      keyId: $sig->keyId,
      signature: $sig->signature,
      algorithm: $sig->algorithm,
      created: $sig->created,
      expires: $sig->expires,
      headers: $sig->headers,
      doc: $sig->doc,
      skew_seconds: $sig->skew_seconds
    );
  }

  public function get_header(string $header) : ?string {
    switch($header){
      case '(request-target)': return $this->doc->method . ' ' . $this->doc->location;
      case '(created)': return ''.$this->created;
      case '(expires)': return ''.$this->expires;
    }
    return @$this->doc->headers[$header];
  }

  public function construct_signature_message() : ?string {
    $result = [];
    foreach($this->headers as $header){
      $value = $this->get_header($header);
      if($value === null)
        return null;
      $result[] = "$header: $value";
    }
    return implode("\n", $result);
  }

  public function verify(
    ?\DateTimeInterface $received_time=null,
    bool $received_time_trusted=false,
    bool $check_message_body=true
  ) : VerificationResult {
    $check_time = $received_time ? $received_time->getTimestamp() : strtotime("now");
    if($received_time_trusted && $received_time !== null && $received_time->getTimestamp() - $this->skew_seconds > strtotime("now")){
      trigger_error("received_time is in the future and received_time_trusted is set to true! Wherever that time came from, it probably should not have been trusted!", E_USER_WARNING);
      return VerificationResult::INVALID;
    }
    if($this->expires !== null && $this->expires - $this->skew_seconds < $check_time)
      return VerificationResult::INVALID;
    if($this->created !== null && $this->created + $this->skew_seconds > $check_time)
      return VerificationResult::INVALID;
    if($this->expires !== null && $this->created !== null && $this->expires < $this->created)
      return VerificationResult::INVALID;
    $key = PublicKey::load($this->keyId);
    if(!$key)
      return VerificationResult::INVALID;
    $sa = SignatureAlgorithm::get($this->algorithm);
    if(!$sa)
      return VerificationResult::INVALID;
    $insecure = $sa->deprecated && (!$received_time_trusted || !$received_time || $received_time >= $sa->deprecated);
    $message = $this->construct_signature_message();
    if(!$message)
      return VerificationResult::INVALID;
    if(!$sa->verify($key, $message, $this->signature))
      return VerificationResult::INVALID;
    $results = [];
    if( $check_message_body && ( $this->doc->message
     || in_array('digest', $this->headers)
     || in_array('repr-digest', $this->headers)
     || in_array('content-digest', $this->headers)
    )){
      // The spec doesn't ask for the checking the content, but let's check it anyway!
      if(in_array('content-digest',$this->headers))
        return VerificationResult::INVALID; // There is no direct access to the non-decoded content, this mustn't be used
      if(in_array('digest',$this->headers))
        $results[] = $this->doc->checkDigest('digest', $received_time_trusted ? $received_time : null);
      if(in_array('repr-digest',$this->headers))
        $results[] = $this->doc->checkDigest('repr-digest', $received_time_trusted ? $received_time : null);
      if(!$results)
        return VerificationResult::INVALID;
    }
    $results[] = !$insecure ? VerificationResult::VALID : VerificationResult::VALID_BUT_INSECURE;
    return VerificationResult::all(...$results);
  }
}
