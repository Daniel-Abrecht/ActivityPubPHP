<?php

require_once __DIR__.'/lib/loader.inc.php';

$router = new \dpa\router\Router();

$router->route('get', ['actor',null], function($name){
  header('Content-Type: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
  print_r([''.\dpa\activitypub\f::actorNameToURL($name)]);
});

$router->route('get', ['actor',null,'inbox'], function($name){
  header('Content-Type: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
  print_r([''.\dpa\activitypub\f::actorNameToURL($name)]);
});

$router->route('get', ['actor',null,'outbox'], function($name){
  header('Content-Type: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
  print_r([''.\dpa\activitypub\f::actorNameToURL($name)]);
});

$router->execute();


//header('Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');

//$request = \dpa\http\HTTPDoc::fromCurrentRequest();
//print_r($request->verify());
