<?php

declare(strict_types = 1);
namespace auto;

spl_autoload_register(function($class_name){
  if(!str_starts_with($class_name, 'auto\\'))
    return;
  $parts = explode('\\', $class_name);
  $name = @explode('_', array_pop($parts), 2)[1];
  if(!$name)
    return;
  $namespace = implode('/', $parts);
  $file = $namespace . '/' . $name . '.php';
  if(file_exists($file))
    require_once $file;
});

interface POJO extends \Serializable {
  public function toArray() : array|string;
  public function fromArray(array|string $data) : void;
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Property {
  function __construct(
    public string $iri,
    public string $name
  ){}
}

require 'xsd.php';

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

class ContextHelper {
  public $mapping = [];
  public function __construct($context=null){
    if($context instanceof self){
      $this->mapping = $context->mapping;
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
    foreach($context as $entry)
    foreach($entry as $key => $value){
      if(!is_string($value))
        continue;
      if(!isset($this->mapping[$key]))
        $this->mapping[$key] = [];
      $this->mapping[$key][] = $value;
    }
  }

  // Note, this is technically all wrong, the referenced context may spcify how to construct the iri,
  // and there is other special stuff like @vocab or @import, but we just assume a few things instead.
  // In practice, this is good enough, and avoids a lot of overhead.
  public function lookup($key){
    @list($prefix, $ref) = explode(':', $key, 2);
    if($ref !== null){
      if(isset($this->mapping[$prefix])){
        $prefix = $this->mapping[$prefix];
      }else if($ref[0] == '/'){
        return lookup_type_by_iri($key);
      }
    }else{
      $prefix = @$this->mapping['@id'];
      $ref = $key;
    }
    if($prefix)
    foreach($prefix as $p){
      $v = lookup_type_by_iri($p, $ref);
      if($v)
        return $v;
    }
  }

  public static function merge(...$contexts){
    $result = new ContextHelper();
    foreach($contexts as $context){
      $c = new ContextHelper($context);
      foreach($c->mapping as $k => $e){
        if(!isset($result->mapping[$k])){
          $result->mapping[$k] = $e;
          continue;
        }
        $result->mapping[$k] = array_merge($result->mapping[$k], $e);
      }
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

function toArrayHelper(POJO $o) : array {
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
      if($value instanceof POJO){
        $result[$entry->name] = toArrayHelper($value);
      }else{
        if(is_array($value) && isset($value[0])){
          foreach($value as &$v)
            if($v instanceof POJO)
              $v = toArrayHelper($v);
        }
        $result[$entry->name] = $value;
      }
    }
    $result['@context'] = $o::NS;
    $result['type'] = $o::TYPE;
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
      if(!isset($a[$entry->name]))
        continue;
      $value = $a[$entry->name];
      if(is_array($value)){
        if(isset($value[0])){
          foreach($value as &$v)
            if(is_array($v))
              $v = fromArray($v, $context);
        }else{
          $value = fromArray($value, $context);
        }
      }
      $o->{'set_'.$key}($value);
    }
  }
}

function fromArray(array $a, $context=null) : ?POJO {
  $type = @$a['type'];
  if(!is_string($type))
    return null;
  $context = ContextHelper::merge(@$a['@context'], $context);
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
  if(count($expand))
    foreach($res as &$v)
      $v = deser($v,$expand);
  return $res;
}

$person = new \auto\www_w3_org\ns\activitystreams\C_Person();
$person->set_preferredUsername("Hello World!");
$image_1 = new \auto\www_w3_org\ns\activitystreams\C_Link();
$image_1->set_href("https://dpa.li/avatar.png");
$image_2 = new \auto\www_w3_org\ns\activitystreams\C_Link();
$image_2->set_href("https://dpa.li/avatar.png");
$person->add_image($image_1,$image_2);
//$person->add_image();
echo $person->serialize() . "\n";

print_r(unserialize('
{
  "@context": "http://www.w3.org/ns/activitystreams",
  "type": "Person",
  "preferredUsername": "Hello World!",
  "image": [
    {
      "@context": "http://www.w3.org/ns/activitystreams",
      "type": "Link",
      "href": "https://dpa.li/avatar.png"
    },
    {
      "@context": "http://www.w3.org/ns/activitystreams",
      "type": "Link",
      "href": "https://dpa.li/avatar.png"
    }
  ]
}
')->serialize()."\n");
