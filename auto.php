<?php

// Note: a lot of things are wrong here.
// Do not worry about that, all the ActivityPub related specs are a giant clusterfuck anyway,
// you can only make things work, you can't make them work correctly.

declare(strict_types = 1);
namespace auto;

const BASE = __DIR__;

function load($file){
  if(file_exists($file))
    require_once $file;
}

spl_autoload_register(function($class_name){
  if(!str_starts_with($class_name, 'auto\\'))
    return;
  $parts = explode('\\', $class_name);
  $name = array_pop($parts);
  if($name == '__module__'){
    $name = '__module__';
  }else{
    $name = @explode('_', $name, 2)[1];
    if(!$name)
      return;
  }
  $namespace = implode('/', $parts);
  $file = $namespace . '/' . $name . '.php';
  load($file);
});

interface POJO extends \Serializable {
  public function toArray($oldns=null) : array|string|null;
  public function fromArray(array $data) : void;
}

interface simple_type extends \Serializable, \Stringable {
  function __construct(string $scalar);
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Property {
  function __construct(
    public string $iri,
    public string $name,
    public string|null $defaultType = null
  ){}
}

function lookup_type_by_iri($iri, $key=null){
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
  $path = preg_replace('/([\\\\])([0-9])/', '\\1_\\2', $path);
  $path = '\auto\\' . $path;
  if(!class_exists($path))
    return null;
  return $path;
}

function lookup_context($uri){
  if(!preg_match('/^([^:]*:(\/\/)?)([^#]*)(#(.*))?$/',$uri,$result))
    return null;
  if(!@$result[3])
    return null;
  $path = explode(@$result[2]?'/':':',$result[3]);
  $path[] = '__module__';
  $path = implode('\\',$path);
  $path = preg_replace('/[^a-zA-Z0-9_\x7f-\xff\\\\]/', '_', $path);
  $path = preg_replace('/([\\\\])([0-9])/', '\\1_\\2', $path);
  $path = '\auto\\' . $path;
  if(!class_exists($path))
    return null;
  return $path::META;
}

class ContextHelper {
  public $mapping = [];
  public $context = [];
  public function __construct($context=null){
    if($context instanceof self){
      $this->mapping = $context->mapping;
      $this->context = $context->context;
      return;
    }
    if(!$context)
      return;
    if(is_string($context))
      $context = [$context];
    if(is_array($context) && count($context))
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
        $this->context[$value] = lookup_context($value);
        if($this->context[$value])
          $this->mapping = array_merge($this->mapping, $this->context[$value]['MAPPING']);
      }else{
        $this->mapping[$key] = $value;
      }
    }
    // TODO: only do this for new entries
    foreach($this->mapping as &$value)
      $value = $this->key2iri($value);
  }

  public function key2iri(string $key) : string {
    if(isset($this->mapping[$key]))
      return $this->mapping[$key];
    @list($prefix, $ref) = explode(':', $key, 2);
    if($ref !== null && isset($this->mapping[$prefix]))
      return $this->mapping[$prefix] . $ref;
    return $key;
  }

  public function iri2key(string $iri) : string{
    $result = $iri;
    $best = 0;
    foreach($this->mapping as $key => $value){
      if($iri == $value)
        return $key;
      if(count($value) >= $best)
        continue;
      if(!str_starts_with($iri, $value))
        continue;
      $best = count($value);
      $result = $key+':'+substr($value,$best);
    }
    return $result;
  }

  public function lookup($key){
    return lookup_type_by_iri($this->key2iri($key));
  }

  public static function merge(...$contexts){
    $result = new ContextHelper();
    foreach($contexts as $context){
      $c = new ContextHelper($context);
      $result->mapping = array_merge($result->mapping, $c->mapping);
      $result->context = array_merge($result->context, $c->context);
    }
    return $result;
  }
}

function getAllParents($reflection, &$list=[]){
  if(is_string($reflection))
    $reflection = new \ReflectionClass($reflection);
  $list[] = $reflection;
  foreach($reflection->getInterfaces() as $interface){
    if(in_array($interface, $list))
      continue;
    getAllParents($interface, $list);
  }
  return $list;
}

function toArrayHelper(POJO $o, $old=null) : array {
  $result = [];
  foreach(getAllParents(get_class($o)) as $reflection){
    $info = [];
    foreach($reflection->getMethods() as $entry){
      if(!($attr = @$entry->getAttributes(Property::class)[0]))
        continue;
      if(!($name = @explode('_', $entry->getName(), 2)[1]))
        continue;
      if(isset($info[$name]))
        continue;
      $info[$name] = $attr->newInstance();
    }
    foreach($info as $key => $entry){
      $value = $o->{'get_'.$key}();
      if($value === null || (is_array($value) && count($value) == 0))
        continue;
      if(!is_array($value) || !isset($value[0]))
        $value = [$value];
      foreach($value as &$v){
        if($v instanceof POJO){
          $v = $v->toArray($v, $o::NS['CONTEXT']);
        }else if($v instanceof simple_type){
          $v = $v->__toString();
        }
      }
      unset($v);
      if(count($value) == 1)
        $value = $value[0];
      $result[$entry->name] = $value;
    }
    if($o::NS['CONTEXT'] && $o::NS['CONTEXT'] != $old)
      $result['@context'] = $o::NS['CONTEXT'];
    $typefield = array_search('@type', $o::NS['MAPPING'], false);
    if(!$typefield)
      $typefield = '@type';
    $result[$typefield] = $o::TYPE;
  }
  return $result;
}

function fromArrayHelper(POJO $o, array $a, ContextHelper $context=null) : void {
  if(!$context)
    $context = ContextHelper::merge(@$a['@context'], $o::IRI);
  $result = [];
  foreach(getAllParents(get_class($o)) as $reflection){
    $info = [];
    foreach($reflection->getMethods() as $entry){
      if(!($attr = @$entry->getAttributes(Property::class)[0]))
        continue;
      if(!($name = @explode('_', $entry->getName(), 2)[1]))
        continue;
      if(isset($info[$name]))
        continue;
      $info[$name] = $attr->newInstance();
    }
    foreach($info as $key => $entry){
      if(isset($a[$entry->name])){
        $value = $a[$entry->name];
      }else if(isset($a[$entry->iri])){
        $value = $a[$entry->iri];
      }else continue;
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
      $o->{'set_'.$key}(...$value);
    }
  }
}

function fromArray(array $a, $context=null, $defaultType=null) : ?POJO {
  $context = ContextHelper::merge($context, @$a['@context']);
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
  fromArrayHelper($pojo, $a, $context);
  return $pojo;
}

function toArray(POJO $o) : array {
  return $o->toArray();
}

function serialize(POJO $o) : string {
  return $o->serialize();
}

function unserialize(string $s) : ?POJO {
  return fromArray(json_decode($s,true));
}

function deser($v, array $a=[]){
  if(!is_string($v) || !count($a))
    return $v;
  try {
    foreach($a as $class)
      return new $class($v);
  } catch(Exception $e) {}
  throw new Exception("Failed to convert string ".json_encode($v)." to any of ".json_encode($a));
}

function array_flatten(array $x, array $expand=[]) : array {
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
      $v = deser($v,$expand);
    unset($v);
  }
  return $res;
}
