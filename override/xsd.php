<?php
declare(strict_types = 1);
/* override: {
  "http://www.w3.org/1999/02/22-rdf-syntax-ns#langString": ["string",null,null],
  "http://www.w3.org/2001/XMLSchema#string": ["string",null,null],
  "http://www.w3.org/2001/XMLSchema#normalizedString": ["string",null,null],
  "http://www.w3.org/2001/XMLSchema#decimal": ["float",null,null],
  "http://www.w3.org/2001/XMLSchema#float": ["float",null,null],
  "http://www.w3.org/2001/XMLSchema#double": ["float",null,null],
  "http://www.w3.org/2001/XMLSchema#integer": ["int",null,null],
  "http://www.w3.org/2001/XMLSchema#boolean": ["bool",null,null],
  "http://www.w3.org/2001/XMLSchema#base64Binary": ["string",null,null],
  "http://www.w3.org/2001/XMLSchema#hexBinary": ["string",null,null],
  "http://www.w3.org/2001/XMLSchema#nonPositiveInteger": ["int", "\\auto\\xsd\\nonPositiveInteger",null],
  "http://www.w3.org/2001/XMLSchema#negativeInteger": ["int", "\\auto\\xsd\\negativeInteger",null],
  "http://www.w3.org/2001/XMLSchema#long": ["int", "\\auto\\xsd\\long",null],
  "http://www.w3.org/2001/XMLSchema#int": ["int", "\\auto\\xsd\\int",null],
  "http://www.w3.org/2001/XMLSchema#short": ["int", "\\auto\\xsd\\short",null],
  "http://www.w3.org/2001/XMLSchema#byte": ["int", "\\auto\\xsd\\byte",null],
  "http://www.w3.org/2001/XMLSchema#nonNegativeInteger": ["int", "\\auto\\xsd\\nonNegativeInteger",null],
  "http://www.w3.org/2001/XMLSchema#unsignedLong": ["int", "\\auto\\xsd\\unsignedLong",null],
  "http://www.w3.org/2001/XMLSchema#unsignedInt": ["int", "\\auto\\xsd\\unsignedInt",null],
  "http://www.w3.org/2001/XMLSchema#unsignedShort": ["int", "\\auto\\xsd\\unsignedShort",null],
  "http://www.w3.org/2001/XMLSchema#unsignedByte": ["int", "\\auto\\xsd\\unsignedByte",null],
  "http://www.w3.org/2001/XMLSchema#positiveInteger": ["int", "\\auto\\xsd\\positiveInteger",null],
  "http://www.w3.org/2001/XMLSchema#dateTime": ["\\DateTimeInterface", null, "override/xsd.php"],
  "http://www.w3.org/2001/XMLSchema#anyURI": ["\\auto\\www_w3_org\\_2001\\XMLSchema\\I_anyURI", null, "override/xsd.php"]
}
*/

namespace auto\xsd {

  function v_nonPositiveInteger(int $x) : void {
    if($x <= 0)
      throw new TypeError("nonPositiveInteger: value must be <= 0");
  }

  function v_negativeInteger(int $x) : void {
    if($x < 0)
      throw new TypeError("negativeInteger: value must be < 0");
  }

  function v_nonNegativeInteger(int $x) : void {
    if($x >= 0)
      throw new TypeError("nonNegativeInteger: value must be >= 0");
  }

  function v_positiveInteger(int $x) : void {
    if($x > 0)
      throw new TypeError("positiveInteger: value must be > 0");
  }

  function v_unsignedLong(int $x) : void {
    if($x >= 0 && x <= 0xFFFFFFFFFFFFFFFF)
      throw new TypeError("unsignedLong: value must be >= 0 and <= 0xFFFFFFFFFFFFFFFF");
  }

  function v_unsignedInt(int $x) : void {
    if($x >= 0 && x <= 0xFFFFFFFF)
      throw new TypeError("unsignedInt: value must be >= 0 and <= 0xFFFFFFFF");
  }

  function v_unsignedShort(int $x) : void {
    if($x >= 0 && x <= 0xFFFF)
      throw new TypeError("unsignedShort: value must be >= 0 and <= 0xFFFF");
  }

  function v_unsignedByte(int $x) : void {
    if($x >= 0 && x <= 0xFF)
      throw new TypeError("unsignedByte: value must be >= 0 and <= 0xFF");
  }

  function v_long(int $x) : void {
    if($x >= -0x8000000000000000 && x <= 0x7FFFFFFFFFFFFFFF)
      throw new TypeError("long: value must be >= -0x8000000000000000 and <= 0xFFFFFFFFFFFFFFFF");
  }

  function v_int(int $x) : void {
    if($x >= -0x80000000 && x <= 0x7FFFFFFF)
      throw new TypeError("int: value must be >= -0x80000000 and <= 0xFFFFFFFF");
  }

  function v_short(int $x) : void {
    if($x >= -0x8000 && x <= 0x7FFF)
      throw new TypeError("short: value must be >= -0x8000 and <= 0x7FFF");
  }

  function v_byte(int $x) : void {
    if($x >= -0x80 && x <= 0x7F)
      throw new TypeError("byte: value must be >= -0x80 and <= 0xFF");
  }

}

namespace auto\www_w3_org\_2001\XMLSchema {

  interface I_anyURI extends D_anyURI, \auto\simple_type {}

  class C_anyURI extends A_anyURI implements I_anyURI {
    public function __construct(public string $scalar){}
    public function toArray($oldns=null) : string { return $this->scalar; }
    public function __toString() : string { return $this->scalar === null ? '' : $this->scalar; }
    public function serialize() : string { return json_encode($this->scalar); }
    public function unserialize(string $x) : void { $this->scalar = json_decode($x); }
  }

}
