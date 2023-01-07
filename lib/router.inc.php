<?php

declare(strict_types = 1);
namespace dpa\router;

class RouteMatch {
  public function __construct(
    public Route $route,
    public array $arguments
  ){}
  public function execute() : void {
    ($this->route->callback)(...$this->arguments);
  }
};

class Router {
  protected array $routes = [];

  public function route(?string $method, array $components, \Closure $callback) : void {
    $this->add(new Route($method, $components, $callback));
  }

  public function add(Route $route) : void {
    $this->routes[] = $route;
  }

  public function lookup(?URLLocation $location=null) : ?RouteMatch {
    if($location === null)
      $location = URLLocation::fromPath(preg_replace('/([\\?\\#].*)?/sm', '', $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? ''), $_SERVER['REQUEST_METHOD']);
    $count = INF;
    $best_route = null;
    foreach($this->routes as $route){
      $match = $route->match($location);
      if(!$match)
        continue;
      $c = count($match->arguments);
      if($count <= $c)
        continue;
      $count = $c;
      $best_route = $match;
    }
    return $best_route;
  }

  public function execute(?URLLocation $location=null) : bool {
    $route = $this->lookup($location);
    if($route){
      $route->execute();
      return true;
    }else{
      http_response_code(404);
      return false;
    }
  }

}

class Route {

  public function __construct(
    public ?string $method,
    public array $components,
    public \Closure $callback
  ){}

  public function match(URLLocation $location) : ?RouteMatch {
    if($this->method !== null && $this->method !== $location->method)
      return null;
    $n = count($location->components);
    if(count($this->components) != $n)
      return null;
    $arguments = [];
    for($i=0; $i<$n; $i++){
      $a = $this->components[$i];
      $b = $location->components[$i];
      if($a === null){
        $arguments[] = $b;
      }else{
        if("$a" !== $b)
          return null;
      }
    }
    return new RouteMatch($this, $arguments);
  }
}

class URLLocation implements \stringable {

  public string $method;
  public array $components = [];

  public function __construct(
    array $components,
    string $method='get',
  ){
    $this->method = $method;
    foreach($components as $component){
      if($component instanceof URLLocation){
        $this->components = array_merge($this->components, $component->components);
      }else{
        $this->components[] = "$component";
      }
    }
  }

  public static function fromPath(string $path, string $method='get') : URLLocation {
    $result = [];
    foreach(explode('/', $path) as $part){
      if($part === '' || $part === '.')
        continue;
      if($part === '..'){
        array_pop($result);
        continue;
      }
      $result[] = static::component_decode($part);
    }
    return new URLLocation($result, strtolower($method));
  }

  public function toPath() : string {
    $params = [];
    foreach($this->components as $component)
      $params[] = static::component_encode($component);
    return '/'.implode('/', $params);
  }

  public function __toString() : string {
    return $this->toPath();
  }

  protected static function component_encode(string $param) : string {
    $param = preg_replace('/^(\\.*)$/', '...\1', $param);
    $param = preg_replace_callback('/[\\?\\/%\\#]/', function(array $match){
      return '%'.bin2hex($match[0]);
    }, $param??'');
    assert($param !== null);
    return $param;
  }

  protected static function component_decode(string $param) : string {
    $param = rawurldecode($param);
    $param = preg_replace('/^\\.\\.\\.(\\.+)$/','\\1', $param);
    assert($param !== null);
    return $param;
  }

};

class URL implements \stringable {
  protected string $origin;
  protected URLLocation $location;
  protected ?array $query = null;
  protected ?string $fragment = null;

  public function __construct(string|URL $origin, string|URLLocation... $parts){
    if(is_string($origin)){
      if(!preg_match('/^([^:\\/]+:\\/\\/[^\\/]+)(\\/[^\\?\\#]*)?(\\?[^\\#]*)?(\\#.*)?$/sm', $origin, $match))
        throw new \Exception("Invalid URL");
      $this->origin = strtolower($match[1]);
      $this->location = URLLocation::fromPath($match[2] ?? '');
      if(@$match[3]){
        $query = [];
        foreach(explode('&',substr($match[3],1)) as $param){
          $param = explode('=', $param, 2);
          $query[rawurldecode($param[0])] = ($param[1]??null) === null ? null : rawurldecode($param[1]);
        }
        $this->query = $query;
      }
      if(@$match[4])
        $this->fragment = substr($match[4],1);
    }else{
      $this->origin = $origin->origin;
      $this->location = $origin->location;
      $this->query = $origin->query;
      $this->fragment = $origin->fragment;
    }
    foreach($parts as $part){
      if(is_string($part)){
        if(!preg_match('/^([^\\?\\#]*)?(\\?[^\\#]*)?(\\#.*)?$/sm', $part, $match))
          throw new \Exception("Invalid URL");
        if(@$match[1])
          $this->location = new URLLocation([$this->location, URLLocation::fromPath($match[1])]);
        if(@$match[2]){
          $query = $this->query ?? [];
          foreach(explode('&',substr($match[2],1)) as $param){
            $param = explode('=', $param, 2);
            $query[rawurldecode($param[0])] = $param[1]??null === null ? null : rawurldecode($param[1]);
          }
          $this->query = $query;
        }
        if(@$match[3])
          $this->fragment = substr($match[3],1);
      }else{
        $this->location = new URLLocation([$this->location, $part]);
      }
    }
  }

  public function getOrigin() : string {
    return $this->origin;
  }

  public function getLocation() : URLLocation {
    return $this->location;
  }

  public function getQuery() : ?array {
    return $this->query;
  }

  public function getFragment() : ?string {
    return $this->fragment;
  }

  public function __toString() : string {
    $res = $this->origin;
    $res .= $this->location;
    if($this->query){
      $parts = [];
      foreach($this->query as $key => $value){
        $str = rawurlencode($key);
        if($value !== null)
          $str .= '=' . rawurlencode($value);
        $parts[] = $str;
      }
      $res .= '?' . implode('&', $parts);
    }
    if($this->fragment)
      $res .= '#' . $this->fragment;
    return $res;
  }

};
