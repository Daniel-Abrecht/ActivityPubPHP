<?php

namespace auto\xsd;

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

class C_URI implements \auto\simple_type {
  public function __construct(public string $scalar){}
  public function toString(){return $this->scalar;}
  public function serialize() : string { return json_encode($this->scalar); }
  public function unserialize(string $x) : void { $this->scalar = json_decode($x); }
}
