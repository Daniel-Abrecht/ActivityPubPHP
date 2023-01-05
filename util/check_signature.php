<?php
require "lib/loader.inc.php";

$keys = \dpa\crypto\PublicKey::getAllFromPEM(file_get_contents(\dpa\BASE . '/test/test.pem'));

$request = \dpa\http\HTTPDoc::fromCurrentRequest();
echo $request->verify($keys,
  check_message_body: !@$_SERVER['HTTP_NBC'],
  received_time: @$_SERVER['HTTP_RT'] ? new DateTimeImmutable($_SERVER['HTTP_RT']) : null,
  received_time_trusted: !!@$_SERVER['HTTP_RTT']
)->name;
