<?php
require "lib/loader.inc.php";
$request = \dpa\http\HTTPDoc::fromCurrentRequest();
echo $request->verify()->name;
