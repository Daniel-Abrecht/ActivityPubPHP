<?php

declare(strict_types = 1);

namespace auto\activitypub;

require 'auto.inc.php';
require 'http.inc.php';

header('Accept: application/ld+json; profile="http://www.w3.org/ns/activitystreams"');

$request = \auto\http\HTTPDoc::fromCurrentRequest();
print_r($request);
print_r($request->verify());
