<?php
declare(strict_types = 1);
/* override: {
  "http://www.w3.org/1999/02/22-rdf-syntax-ns#langString": ["string",null,null,"VARCHAR(512)"],
  "http://www.w3.org/2001/XMLSchema#string": ["string",null,null,"VARCHAR(512)"],
  "http://www.w3.org/2001/XMLSchema#normalizedString": ["string",null,null,"VARCHAR(512)"],
  "http://www.w3.org/2001/XMLSchema#decimal": ["float",null,null,"DOUBLE"],
  "http://www.w3.org/2001/XMLSchema#float": ["float",null,null,"FLOAT"],
  "http://www.w3.org/2001/XMLSchema#double": ["float",null,null,"DOUBLE"],
  "http://www.w3.org/2001/XMLSchema#integer": ["int",null,null,"BIGINT"],
  "http://www.w3.org/2001/XMLSchema#boolean": ["bool",null,null,"BOOLEAN"],
  "http://www.w3.org/2001/XMLSchema#base64Binary": ["string",null,null,"BLOB"],
  "http://www.w3.org/2001/XMLSchema#hexBinary": ["string",null,null,"BLOB"],
  "http://www.w3.org/2001/XMLSchema#nonPositiveInteger": ["int", "\\dpa\\override\\xsd\\nonPositiveInteger",null,"BIGINT CHECK({} <= 0)"],
  "http://www.w3.org/2001/XMLSchema#negativeInteger": ["int", "\\dpa\\override\\xsd\\negativeInteger",null,"BIGINT CHECK({} < 0)"],
  "http://www.w3.org/2001/XMLSchema#long": ["int", "\\dpa\\override\\xsd\\long",null,"BIGINT"],
  "http://www.w3.org/2001/XMLSchema#int": ["int", "\\dpa\\override\\xsd\\int",null,"INT"],
  "http://www.w3.org/2001/XMLSchema#short": ["int", "\\dpa\\override\\xsd\\short",null,"SMALLINT"],
  "http://www.w3.org/2001/XMLSchema#byte": ["int", "\\dpa\\override\\xsd\\byte",null,"TINYINT"],
  "http://www.w3.org/2001/XMLSchema#nonNegativeInteger": ["int", "\\dpa\\override\\xsd\\nonNegativeInteger",null,"BIGINT UNSIGNED"],
  "http://www.w3.org/2001/XMLSchema#unsignedLong": ["int", "\\dpa\\override\\xsd\\unsignedLong",null,"BIGINT UNSIGNED"],
  "http://www.w3.org/2001/XMLSchema#unsignedInt": ["int", "\\dpa\\override\\xsd\\unsignedInt",null,"INT UNSIGNED"],
  "http://www.w3.org/2001/XMLSchema#unsignedShort": ["int", "\\dpa\\override\\xsd\\unsignedShort",null,"SMALLINT UNSIGNED"],
  "http://www.w3.org/2001/XMLSchema#unsignedByte": ["int", "\\dpa\\override\\xsd\\unsignedByte",null,"TINYINT UNSIGNED"],
  "http://www.w3.org/2001/XMLSchema#positiveInteger": ["int", "\\dpa\\override\\xsd\\positiveInteger",null,"BIGINT UNSIGNED CHECK({} > 0)"],
  "http://www.w3.org/2001/XMLSchema#dateTime": ["\\DateTimeInterface", "\\dpa\\pojo\\www_w3_org\\_2001\\XMLSchema\\C_dateTime::fixup", "lib/override/xsd.php","DATETIME"],
  "http://www.w3.org/2001/XMLSchema#anyURI": ["\\dpa\\pojo\\www_w3_org\\_2001\\XMLSchema\\I_anyURI", null, "lib/override/xsd.php","VARCHAR(512)"]
}
*/

namespace dpa\override\xsd {

  function v_nonPositiveInteger(int $x) : void {
    if($x <= 0)
      throw new \TypeError("nonPositiveInteger: value must be <= 0");
  }

  function v_negativeInteger(int $x) : void {
    if($x < 0)
      throw new \TypeError("negativeInteger: value must be < 0");
  }

  function v_nonNegativeInteger(int $x) : void {
    if($x >= 0)
      throw new \TypeError("nonNegativeInteger: value must be >= 0");
  }

  function v_positiveInteger(int $x) : void {
    if($x > 0)
      throw new \TypeError("positiveInteger: value must be > 0");
  }

  function v_unsignedLong(int $x) : void {
    if($x >= 0 && $x <= 0xFFFFFFFFFFFFFFFF)
      throw new \TypeError("unsignedLong: value must be >= 0 and <= 0xFFFFFFFFFFFFFFFF");
  }

  function v_unsignedInt(int $x) : void {
    if($x >= 0 && $x <= 0xFFFFFFFF)
      throw new \TypeError("unsignedInt: value must be >= 0 and <= 0xFFFFFFFF");
  }

  function v_unsignedShort(int $x) : void {
    if($x >= 0 && $x <= 0xFFFF)
      throw new \TypeError("unsignedShort: value must be >= 0 and <= 0xFFFF");
  }

  function v_unsignedByte(int $x) : void {
    if($x >= 0 && $x <= 0xFF)
      throw new \TypeError("unsignedByte: value must be >= 0 and <= 0xFF");
  }

  function v_long(int $x) : void {
    if($x >= -0x8000000000000000 && $x <= 0x7FFFFFFFFFFFFFFF)
      throw new \TypeError("long: value must be >= -0x8000000000000000 and <= 0xFFFFFFFFFFFFFFFF");
  }

  function v_int(int $x) : void {
    if($x >= -0x80000000 && $x <= 0x7FFFFFFF)
      throw new \TypeError("int: value must be >= -0x80000000 and <= 0xFFFFFFFF");
  }

  function v_short(int $x) : void {
    if($x >= -0x8000 && $x <= 0x7FFF)
      throw new \TypeError("short: value must be >= -0x8000 and <= 0x7FFF");
  }

  function v_byte(int $x) : void {
    if($x >= -0x80 && $x <= 0x7F)
      throw new \TypeError("byte: value must be >= -0x80 and <= 0xFF");
  }

}

namespace dpa\pojo\www_w3_org\_2001\XMLSchema {

  interface I_anyURI extends D_anyURI, \dpa\jsonld\simple_type {}

  class C_anyURI extends A_anyURI implements I_anyURI {
    public function __construct(public ?string $scalar){}
    public function toArray(\dpa\jsonld\ContextHelper $context=null) : ?string { return $this->scalar; }
    public function __toString() : string { return $this->scalar === null ? '' : $this->scalar; }
    public function serialize() : ?string { $a=json_encode($this->scalar); return $a!==false?$a:null; }
    public function unserialize(string $x) : void { $this->scalar = json_decode($x); } // @phpstan-ignore-line
  }

  class C_dateTime extends \DateTimeImmutable implements \DateTimeInterface, \dpa\jsonld\POJO, \dpa\jsonld\simple_type {
    public function __construct(\DateTimeInterface|string|null $input=null){
      if($input instanceof \DateTimeInterface){
        parent::__construct($input->format('Y-m-d\\TH:i:sp'));
      }else if($input !== null){
        parent::__construct($input);
      }else{
        parent::__construct();
      }
    }
    public function fromArray(array $data) : void { \dpa\jsonld\f::fromArrayHelper($this, $data); }
    public function toArray(\dpa\jsonld\ContextHelper $context=null) : string { return $this->__toString(); }
    public function __toString() : string { return $this->format('Y-m-d\\TH:i:sp'); }
    public function serialize() : ?string { $a=json_encode($this->__toString()); return $a!==false?$a:null; }
    public function unserialize(string $x) : void { $this->__construct(json_decode($x)); } // @phpstan-ignore-line
    public static function fixup(\DateTimeInterface &$value) : bool {
      if(!($value instanceof \dpa\jsonld\POJO) && !($value instanceof \dpa\jsonld\simple_type))
        $value = new self($value);
      return true;
    }
  }

}
