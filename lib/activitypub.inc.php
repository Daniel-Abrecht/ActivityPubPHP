<?php

declare(strict_types = 1);
namespace dpa\activitypub;

const SHORT_TYPE = [
  'application' => 'https://www.w3.org/ns/activitystreams#Application',
  'person' => 'https://www.w3.org/ns/activitystreams#Person',
  'organization' => 'https://www.w3.org/ns/activitystreams#Organization',
  'group' => 'https://www.w3.org/ns/activitystreams#Group',
  'service' => 'https://www.w3.org/ns/activitystreams#Service',
];

class Config {
  private static ?Config $self = null;
  private function __construct(
    public readonly \dpa\router\URL $location
  ){}
  public static function get() : Config {
    if(self::$self)
      return self::$self;
    $baseconfig = \dpa\config\Config::get();
    $json = $baseconfig->json['activitypub'] ?? [];
    $location = new \dpa\router\URL($baseconfig->origin, $json['location'] ?? '.well-known/activitypub');
    self::$self = new Config(location: $location);
    return self::$self;
  }
}

class f {

  public static function getActorClass(string $iri) : ?string {
    $class = \dpa\jsonld\f::lookup_type_by_iri(SHORT_TYPE[$iri]??$iri);
    if(!$class)
      return null;
    if(!is_a($class, '\\dpa\\pojo\\www_w3_org\\ns\\activitystreams\\I_Actor', true))
      return null;
    return $class;
  }

  public static function locationToURL(\dpa\router\URLLocation $location) : \dpa\router\URL {
    $config = Config::get();
    return new \dpa\router\URL($config->location, $location);
  }

  public static function actorNameToURL(string $name) : \dpa\router\URL {
    $location = new \dpa\router\URLLocation(['actor',$name]);
    return f::locationToURL($location);
  }

};
