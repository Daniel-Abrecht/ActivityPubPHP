<?php

declare(strict_types = 1);
namespace dpa\jsonld;

interface POJO extends \Serializable {
  const IRI = null;
  const NS = null;
  public function toArray(ContextHelper $context=null) : array|string|null;
  public function fromArray(array $data) : void;
}

interface simple_type extends \Serializable, \Stringable {
  function __construct(string $scalar);
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Property {
  public ?string $name = null;
  function __construct(
    public string $iri,
    public string|null $defaultType = null,
    public array $context = []
  ){}
}

function lookup_type_by_iri(string $iri, ?string $key=null) : ?string {
  if(!preg_match('/^([^:]*:(\/\/)?)([^#]*)(#(.*))?$/',$iri,$result))
    return null;
  if(!@$result[3])
    return null;
  $path = explode(@$result[2]?'/':':',$result[3]);
  if(!$key) $key = @$result[5];
  if($key) $path[] = $key;
  if(count($path) != 1)
    $path[count($path)-1] = 'C_'.$path[count($path)-1];
  $path = implode('\\',$path);
  $path = preg_replace('/[^a-zA-Z0-9_\x7f-\xff\\\\]/', '_', $path);
  $path = preg_replace('/([\\\\])([0-9])/', '\\1_\\2', $path??'');
  $path = '\\dpa\\pojo\\' . $path;
  if(!class_exists($path))
    return null;
  return $path;
}

function lookup_context(string $uri) : ?array {
  if(!preg_match('/^([^:]*:(\/\/)?)([^#]*)(#(.*))?$/',$uri,$result))
    return null;
  if(!@$result[3])
    return null;
  $path = explode(@$result[2]?'/':':',$result[3]);
  $path[] = '__module__';
  $path = implode('\\',$path);
  $path = preg_replace('/[^a-zA-Z0-9_\x7f-\xff\\\\]/', '_', $path);
  $path = preg_replace('/([\\\\])([0-9])/', '\\1_\\2', $path??'');
  $path = '\\dpa\\pojo\\' . $path;
  if(!class_exists($path))
    return null;
  return $path::META;
}

class ContextHelper {
  public array $context = [];
  public array $mapping = [];
  public array $mapping_ext = [];
  public function __construct(ContextHelper|string|array|null $context=null){
    if($context instanceof self){
      $this->context = $context->context;
      $this->mapping = $context->mapping;
      $this->mapping_ext = $context->mapping_ext;
      return;
    }
    if(!$context)
      return;
    if(!is_array($context))
      $context = [$context];
    foreach($context as &$value)
      if(!is_array($value))
        $value = ['@id' => $value];
    unset($value);
    foreach($context as $entry)
    foreach($entry as $key => $value){
      if(!is_string($value)){
        if(is_array($value) && is_string(@$value['@id'])){
          $value = $value['@id'];
        }else{
          continue;
        }
      }
      if($key == '@id'){
        $lctx = lookup_context($value);
        $this->context[$value] = $lctx;
        if($lctx){
          $this->mapping = array_merge($this->mapping, $lctx['MAPPING']);
          $this->mapping_ext = array_merge($this->mapping, $lctx['MAPPING_EXT']);
        }
      }else{
        unset($this->mapping[$key]);
        $this->mapping_ext[$key] = $value;
      }
    }
    foreach($this->mapping as &$value)
      $value = $this->key2iri($value);
    unset($value);
  }

  public function key2iri(string $key) : string {
    $mappings = $this->mapping + $this->mapping_ext;
    while(true){
      if(isset($mappings[$key])){
        $key = $mappings[$key];
        continue;
      }
      @list($prefix, $ref) = explode(':', $key, 2);
      if($ref !== null && isset($mappings[$prefix])){
        $key = $mappings[$prefix] . $ref;
        continue;
      }
      break;
    }
    return $key;
  }

  public function iri2key(string $iri, array &$emaps=null) : string{
    $mappings = $this->mapping + $this->mapping_ext;
    $result = $iri;
    $best = 0;
    foreach($mappings as $key => $value){
      if($iri == $value)
        return $key;
      if(strlen($value) <= $best)
        continue;
      if(!str_starts_with($iri, $value))
        continue;
      $best = strlen($value);
      $suffix = substr($iri,$best);
      $result = $key.':'.$suffix;
      if($emaps !== null && !isset($this->mapping[$key])){
        $emaps[$key] = $value;
        if(!isset($mappings[$suffix])){
          $emaps[$suffix] = $result;
          $result = $suffix;
        }
      }
    }
    return $result;
  }

  public function lookup(string $key) : ?string {
    return lookup_type_by_iri($this->key2iri($key));
  }

  public function merge(ContextHelper|string|array|null ...$contexts) : ContextHelper {
    foreach($contexts as $context){
      $c = new ContextHelper($context);
      $this->context = array_merge($this->context, $c->context);
      $this->mapping = array_merge($this->mapping, $c->mapping);
      $this->mapping_ext = array_merge($this->mapping_ext, $c->mapping_ext);
    }
    return $this;
  }
}

function getAllParents(object|string $reflection, array &$list=[]) : array {
  if(!($reflection instanceof \ReflectionClass))
    $reflection = new \ReflectionClass($reflection); // @phpstan-ignore-line
  $list[] = $reflection;
  foreach($reflection->getInterfaces() as $interface){
    if(in_array($interface, $list))
      continue;
    getAllParents($interface, $list);
  }
  return $list;
}

function g_compact_sub(array $in, ContextHelper $context, array &$emaps) : array {
  $out = [];
  foreach($in as $key => $value){
    if($key === '@type')
      $value = $context->iri2key($value, $emaps);
    if(is_string($key))
      $key = $context->iri2key($key, $emaps);
    if(is_array($value))
      $value = g_compact_sub($value, $context, $emaps);
    $out[$key] = $value;
  }
  return $out;
}

function g_compact(array $in, ContextHelper $context) : array {
  $emaps = [];
  $pmap = [];
  $res = g_compact_sub($in, $context, $pmap);
  $actx = array_keys($context->context);
  if(count($pmap))
    $actx[] = $pmap;
  $out = [];
  if(count($actx) == 1){
    $out['@context'] = $actx[0];
  }else{
    $out['@context'] = $actx;
  }
  $out = $out + $res;
  return $out;
}

function toArrayHelper(POJO $o, ContextHelper $context=null) : array {
  $toplevel = !$context;
  if(!$context)
    $context = new ContextHelper();
  $map = getIRItoNameMap($o);
  $result = [];
  $contexts = [];
  foreach(@$o::NS??[] as $ctx)
    $contexts[$ctx] = INF;
  foreach($map as $entry){
    $value = $o->{'get_'.$entry->name}();
    if($value === null || (is_array($value) && count($value) == 0))
      continue;
    if(!is_array($value) || !isset($value[0]))
      $value = [$value];
    foreach($value as &$v){
      if($v instanceof POJO){
        $v = $v->toArray($context);
      }else if($v instanceof simple_type){
        $v = $v->__toString();
      }
    }
    unset($v);
    if(count($value) == 1)
      $value = $value[0];
    if(!isset($result[$entry->iri])){
      $result[$entry->iri] = $value;
      foreach($entry->context as $ctx)
        $contexts[$ctx] = @$contexts[$ctx] + 1;
    }
  }
  asort($contexts);
  $context->merge(...array_keys($contexts)); // @phpstan-ignore-line
  $result = ['@type'=>$o::IRI] + $result;
  if($toplevel)
    return g_compact($result, $context);
  return $result;
}

function getIRItoNameMap(POJO|string $o) : array {
  static $iri_map = [];
  if(is_object($o))
    $o = get_class($o);
  if(isset($iri_map[$o]))
    return $iri_map[$o];
  $info = [];
  foreach(getAllParents($o) as $reflection){
    foreach($reflection->getMethods() as $entry){
      if(!($attr = @$entry->getAttributes(Property::class)[0]))
        continue;
      if(!($name = @explode('_', $entry->getName(), 2)[1]))
        continue;
      $prop = $attr->newInstance();
      $prop->name = $name;
      $info[preg_replace('/^https?:/','http?:',$prop->iri)] = $prop; // @phpstan-ignore-line
    }
  }
  ksort($info);
  $iri_map[$o] = $info;
  return $info;
}

function iri_map_get(array $iri_map, string $iri) : ?Property {
  return @$iri_map[preg_replace('/^https?:/','http?:',$iri)];
}

function fromArrayHelper(POJO $o, array $a, ContextHelper $context=null) : void {
  if(!$context)
    $context = (new ContextHelper())->merge(@$a['@context'], $o::IRI);
  $iri_map = getIRItoNameMap($o);
  foreach($a as $key => $value){
    $key = $context->key2iri($key);
    $entry = iri_map_get($iri_map, $key);
    if(!$entry){
      if($key[0] != '@')
        trigger_error("No mapping for IRI: $key\n");
      continue;
    }
    if(is_array($value)){
      if(isset($value[0])){
        foreach($value as &$v)
          if(is_array($v))
            $v = fromArray($v, $context, $entry->defaultType);
        unset($v);
      }else{
        $value = [fromArray($value, $context, $entry->defaultType)];
      }
    }else{
      $value = [$value];
    }
    $o->{'set_'.$entry->name}(...$value);
  }
}

function fromArray(array $a, ContextHelper|array|string $context=null, ?string $defaultType=null) : ?POJO {
  $context = (new ContextHelper())->merge($context, @$a['@context']);
  $typefields = array_merge(['@type'], array_keys($context->mapping, '@type', true));
  foreach($typefields as $typefield){
    $type = @$a[$typefield];
    if(is_string($type))
      break;
  }
  if(!is_string($type))
    $type  = $defaultType;
  if(!is_string($type))
    return null;
  $class = $context->lookup($type);
  if(!$class)
    return null;
  $pojo = new $class();
  assert($pojo instanceof POJO);
  fromArrayHelper($pojo, $a, $context);
  return $pojo;
}

function toArray(POJO $o) : array|string|null {
  return $o->toArray();
}

function serialize(POJO $o) : string {
  return $o->serialize();
}

function unserialize(string $s) : ?POJO {
  $a = json_decode($s,true);
  if(!is_array($a))
    return null;
  return fromArray($a);
}

function deser_sub(mixed $v, array $a=[]) : mixed {
  foreach($a as $class) try {
    return new $class($v);
  } catch(\Exception|\Throwable $e) {}
  return $v;
}

function deser(mixed $v, array $a=[], array $mod=[]){ // @phpstan-ignore-line
  $v = deser_sub($v, $a);
  foreach($mod as list($ns,$md)){
    if($v instanceof $ns && !$md($v))
      throw new \Exception("Constraint failed: $md(".json_encode($v).")");
  }
  return $v;
}

function array_flatten(array $x, array $expand=[], array $mod=[]) : array {
  $res = [];
  foreach($x as $vs){
    if($vs === null)
      continue;
    if(!is_array($vs) || (!isset($vs[0]) && count($vs))){
      $res[] = $vs;
      continue;
    }
    foreach(array_flatten($vs) as $v)
      $res[] = $v;
  }
  if(count($expand)){
    foreach($res as &$v)
      $v = deser($v, $expand, $mod);
    unset($v);
  }
  return $res;
}
