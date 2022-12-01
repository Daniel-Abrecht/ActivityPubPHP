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
  $result = [
    '@context' => $o::IRI
  ];
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
      $result[$entry->name] = $value;
    }
  }
  return $result;
}

function fromArrayHelper(POJO $o, array $s) : void {
}

function fromArray(array $s) : POJO {
}

function toArray(POJO $o) : array {
  return $o->toArray();
}

function serialize(POJO $o) : string {
  return $o->serialize();
}

function unserialize(string $s) : POJO {
  return fromArray(json_decode($s));
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
