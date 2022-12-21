<?php

declare(strict_types = 1);
require 'auto.php';

$person = new \auto\www_w3_org\ns\activitystreams\C_Person();
$person->set_preferredUsername("Hello World!");
$image_1 = new \auto\www_w3_org\ns\activitystreams\C_Link();
$image_1->set_href("https://dpa.li/avatar.png");
$image_2 = new \auto\www_w3_org\ns\activitystreams\C_Link();
$image_2->set_href("https://dpa.li/avatar.png");
$person->add_image($image_1,$image_2,"https://dpa.li/avatar.png");
//$person->add_image();
echo $person->serialize() . "\n";

print_r(\auto\unserialize('
{
  "@context": [
    "https://www.w3.org/ns/activitystreams",
    "https://w3id.org/security/v1"
  ],
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
    },
    "https://dpa.li/avatar.png"
  ],
  "publicKey": {
    "id": "https://mastodon.social/users/Gargron#main-key",
    "owner": "https://mastodon.social/users/Gargron",
    "publicKeyPem": "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvXc4vkECU2/CeuSo1wtn\nFoim94Ne1jBMYxTZ9wm2YTdJq1oiZKif06I2fOqDzY/4q/S9uccrE9Bkajv1dnkO\nVm31QjWlhVpSKynVxEWjVBO5Ienue8gND0xvHIuXf87o61poqjEoepvsQFElA5ym\novljWGSA/jpj7ozygUZhCXtaS2W5AD5tnBQUpcO0lhItYPYTjnmzcc4y2NbJV8hz\n2s2G8qKv8fyimE23gY1XrPJg+cRF+g4PqFXujjlJ7MihD9oqtLGxbu7o1cifTn3x\nBfIdPythWu5b4cujNsB3m3awJjVmx+MHQ9SugkSIYXV0Ina77cTNS0M2PYiH1PFR\nTwIDAQAB\n-----END PUBLIC KEY-----\n"
  }
}
')->serialize()."\n");

print_r(\auto\unserialize('
{
  "@context": [
    "https://www.w3.org/ns/activitystreams",
    {
      "toot": "http://joinmastodon.org/ns#",
      "Emoji": "toot:Emoji"
    }
  ],

  "id": "https://example.com/@alice/hello-world",
  "type": "Note",
  "content": "Hello world :kappa:",
  "tag": [
    {
      "id": "https://example.com/emoji/123",
      "type": "Emoji",
      "name": ":kappa:",
      "icon": {
        "type": "Image",
        "mediaType": "image/png",
        "url": "https://example.com/files/kappa.png"
      }
    }
  ],
  "attachment": [
    {
      "type": "Image",
      "mediaType": "image/png",
      "url": "https://example.com/files/cats.png",
      "toot:focalPoint": [-0.55,0.43]
    }
  ]
}
')->serialize()."\n");
