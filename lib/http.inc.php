<?php

declare(strict_types = 1);
namespace dpa\http;

enum VerificationResult {
  case INVALID;
  case VALID;
  case NO_SIGNATURE;
};

class HTTPDoc {
  private static ?HTTPDoc $br = null;
  public ?HTTPSignature $signature = null;
  public string $message;
  public array $headers = [];
  public function __construct(
    ?string $message = null,
    array $headers = []
  ){
    $this->message = $message ?? '';
    $this->headers = array_change_key_case($headers, CASE_LOWER);
    $this->signature = HTTPSignature::load($this);
    $this->trim();
  }
  // Remove unnecessary headers & stuff
  private function trim(){
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
  public static function fromCurrentRequest(){
    if(self::$br)
      return self::$br;
    $message = file_get_contents('php://input');
    self::$br = new HTTPDoc($message, getallheaders());
    return self::$br;
  }
  public function verify() : VerificationResult {
    if(!$this->signature)
      return VerificationResult::NO_SIGNATURE;
    return $this->signature->verify() ? VerificationResult::VALID : VerificationResult::INVALID;
  }
};

function parse_http_signature(?string $sigstr) : ?array {
  if(!$sigstr)
    return null;
  if($sigstr && str_starts_with($sigstr, 'Signature '))
    $sigstr = trim(explode(' ', $sigstr, 2)[1]);
  if(!preg_match_all('/([^=, \t]*(=?("([^"\\\\]|\\\\.)+"|[^=, \t"])))/m', $sigstr, $matches, PREG_PATTERN_ORDER))
    return null;
  $matches = $matches[0];
  $dict = [];
  foreach($matches as $str){
    @list($key, $value) = explode('=', $str, 2);
    if($value[0] == '"'){
      $value = json_decode($value);
      if(!$value)
        return null;
    }else if(ctype_digit($value)){
      $value = +$value;
    }
    if(is_string($value))
      $value = trim($value);
    if(isset($dict[$key]))
      return null;
    $dict[$key] = $value;
  }
  return $dict;
}

enum KeyType : int {
  case UNKNOWN = -1;
  case RSA = 0;
  case DSA = 1;
  case DH = 2;
  case EC = 4;
};

assert(KeyType::RSA == OPENSSL_KEYTYPE_RSA);
assert(KeyType::DSA == OPENSSL_KEYTYPE_DSA);
assert(KeyType::DH == OPENSSL_KEYTYPE_DH);
assert(KeyType::EC == OPENSSL_KEYTYPE_EC);

abstract class Key {
  public function __construct(\OpenSSLAsymmetricKey $key){
    $this->key = $key;
  }
  public \OpenSSLAsymmetricKey $key;
  private static function load_key_string(string $uri) : ?string {
    if($uri === 'Test'){
      $uri = \dpa\BASE . '/test/test.pem';
    }else if(!str_starts_with($uri, 'https://')){
      trigger_error("Will not handle key from: "+$uri, E_USER_WARNING);
      return null;
    }
    return file_get_contents($uri);
  }
  static public function load(string $uri) : ?Key {
    $key = static::load_key_string($uri);
    if(!$key)
      return null;
    return static::fromString($key);
  }
  public function getType() : KeyType {
    return KeyType::from(openssl_pkey_get_details($this->key)['type']);
  }
  static public abstract function fromString(string $uri) : ?Key;
};

class PublicKey extends Key {
  static public function fromString(string $string) : ?PublicKey {
    $key = openssl_pkey_get_public($string);
    if(!$key)
      return null;
    return new PublicKey($key);
  }
  public function __toString() : string {
    $result = openssl_pkey_get_details($this->key);
    return @$result['key'] ?? '';
  }
};

class PrivateKey extends Key {
  static public function fromString(string $string) : ?PrivateKey {
    $key = openssl_pkey_get_private($string);
    if(!$key)
      return null;
    return new PrivateKey($key);
  }
  public function __toString() : string {
    $result = null;
    openssl_pkey_export($this->key, $result);
    return $result ?? '';
  }
};

class HTTPSignature {
  public bool $is_auth = false;
  public string $keyId;
  public string $signature;
  public ?string $algorithm = null; // Note: Do not trust this value
  public ?int $created = null;
  public ?int $expires = null;
  public array $headers = [];
  public HTTPDoc $doc;
  public static function load(HTTPDoc $doc){
    $sig = new HTTPSignature();
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
      $sig->signature = @$dict['signature'];
      $sig->algorithm = @$dict['algorithm'];
      $created = @$dict['created'];
      if(!$created && isset($doc->headers['date']))
        $created = strtotime($doc->headers['date']);
      $sig->created = $created;
      $sig->expires = @$dict['expires'];
      $sig->headers = array_map('strtolower', explode(' ', @$dict['headers'] ? $dict['headers'] : "(created)"));
    } catch(Exception $e) {
      return null;
    }

    if(!$sig->keyId || !$sig->signature || !$sig->headers)
      return null;
    return $sig;
  }

  public function get_header($header) : ?string {
    switch($header){
      case '(request-target)': return $this->doc->method + ' ' + $this->doc->location;
      case '(created)': return ''.$this->created;
      case '(expires)': return ''.$this->expires;
    }
    return @$this->doc->headers[$header];
  }

  public function construct_signature_message(){
    $result = '';
    foreach($this->headers as $header){
      $value = $this->get_header($header);
      if($value !== null)
        $result .= "$header: $value\n";
    }
    $result .= "\n";
    $result .= $this->doc->message;
    return $result;
  }

  public function verify() : bool {
    $key = PublicKey::load($this->keyId);
    if(!$key)
      return false;
    echo $key->getType()->name."\n";
    echo $this->construct_signature_message();
    echo "\n";
    return false;
  }
}
