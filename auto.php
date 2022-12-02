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
  require_once $file;
});

interface POJO extends \Serializable {
  public function toArray() : array;
  public function fromArray(array $data) : void;
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Property {
  function __construct(
    public string $iri,
    public string $name
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

class ContextHelper {
  public $mapping = [];
  public function __construct($context){
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
      $result[$entry->name] = $value;
    }
    $result['@context'] = $o::NS;
    $result['type'] = $o::TYPE;
  }
  return $result;
}

function fromArrayHelper(POJO $o, array $a) : void {
}

function fromArray(array $a) : ?POJO {
  $type = @$a['type'];
  if(!is_string($type))
    return null;
  $context = new ContextHelper(@$a['@context']);
  $class = $context->lookup($type);
  if(!$class)
    return null;
  $pojo = new $class();
  fromArrayHelper($pojo, $a);
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

function array_flatten(array $x) : array {
  if(count($x) == 1 && $x[0] === null)
    return [];
  return iterator_to_array(
    new RecursiveIteratorIterator(new RecursiveArrayIterator($x)),
    false
  );
}

$image = new \auto\www_w3_org\ns\activitystreams\C_Person();
echo $image->serialize() . "\n";

print_r(unserialize('
{
  "@context": "http://www.w3.org/ns/activitystreams",
  "type": "Person"
}
')->serialize()."\n");
