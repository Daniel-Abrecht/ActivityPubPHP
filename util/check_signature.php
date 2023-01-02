<?php
require "lib/loader.inc.php";
$request = \dpa\http\HTTPDoc::fromCurrentRequest();
echo $request->verify(check_message_body: !@$_SERVER['HTTP_NBC'])->name;
