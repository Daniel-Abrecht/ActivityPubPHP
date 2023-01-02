<?php

declare(strict_types = 1);
namespace dpa;

const BASE = __DIR__ . '/..';

function load(string $file) : void {
  if(file_exists(BASE.'/'.$file))
    require_once BASE.'/'.$file;
}

spl_autoload_register(function(string $class_name){
  if(!str_starts_with($class_name, 'dpa\\'))
    return;
  $parts = explode('\\', $class_name);
  if(count($parts) <= 2)
    return;
  if($parts[1] === 'pojo'){
    array_shift($parts);
    $name = array_pop($parts);
    if($name != '__module__'){
      if(($name[1]??'') == '_'){
        $name = explode('_', $name??'', 2)[1];
      }else return;
    }
    $parts[] = $name;
  }else{
    array_pop($parts);
    $parts[0] = 'lib';
  }
  $file = implode('/', $parts) . '.inc.php';
  load($file);
});
