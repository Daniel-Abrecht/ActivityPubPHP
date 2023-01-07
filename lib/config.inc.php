<?php

declare(strict_types = 1);
namespace dpa\config;

class Config {
  private static ?Config $self = null;
  private function __construct(
    public array $json,
    public string $origin
  ){}
  public static function get() : Config {
    if(self::$self)
      return self::$self;
    $json = [];
    if($files=glob(\dpa\BASE . '/config/*.json'))
    foreach($files as $file)
      if($content=file_get_contents($file))
        $json[] = json_decode($content, true);
    $json = array_merge(...$json); // @phpstan-ignore-line
    self::$self = new Config(
      json: $json,
      origin: $json['origin'] // @phpstan-ignore-line
    );
    return self::$self;
  }
};
