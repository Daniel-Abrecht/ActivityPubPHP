<?php
require "lib/loader.inc.php";

$keys = \dpa\crypto\PublicKey::getAllFromPEM(file_get_contents(\dpa\BASE . '/test/test.pem'));

$request = \dpa\http\HTTPDoc::fromCurrentRequest();
echo $request->verify($keys,
  check_message_body: !@$_SERVER['HTTP_NBC']
)->name;
