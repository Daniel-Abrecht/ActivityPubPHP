<?php

declare(strict_types = 1);
require 'auto.php';

$person = new \auto\www_w3_org\ns\activitystreams\C_Person();
$person->set_preferredUsername("Hello World!");
$image_1 = new \auto\www_w3_org\ns\activitystreams\C_Link();
$image_1->set_href("https://dpa.li/avatar.png");
$image_2 = new \auto\www_w3_org\ns\activitystreams\C_Link();
$image_2->set_href("https://dpa.li/avatar.png");
$person->add_image($image_1,$image_2/*,"https://dpa.li/avatar.png"*/);
//$person->add_image();
echo $person->serialize() . "\n";

print_r(\auto\unserialize('
{
  "@context": "http://www.w3.org/ns/activitystreams",
  "type": "Person",
  "preferredUsername": "Hello World!",
  "icon": [
    {
      "type": "Link",
      "http://www.w3.org/ns/activitystreams#href": "https://dpa.li/avatar.png"
    },
    {
      "@context": "http://www.w3.org/ns/activitystreams",
      "@type": "http://www.w3.org/ns/activitystreams#Link",
      "href": "https://dpa.li/avatar.png"
    }
  ]
}
')->serialize()."\n");
