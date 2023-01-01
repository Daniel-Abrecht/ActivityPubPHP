<?php

declare(strict_types = 1);
namespace dpa\activitypub;

require 'lib/loader.inc.php';

header('Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"');

$request = \dpa\http\HTTPDoc::fromCurrentRequest();
print_r($request->verify());
