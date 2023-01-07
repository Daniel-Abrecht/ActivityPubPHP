<?php

declare(strict_types = 1);
namespace dpa\router;

class RouteMatch {
  public function __construct(
    public \Closure $callback,
    public array $arguments
  ){}
  public function execute() : void {
    ($this->callback)(...$this->arguments);
  }
};

class Router {
  protected array $routes = [];

  public function add(string $path, \Closure $handler) : void {
    $path = preg_replace('/[.\\\\\\/+*?\\[^\\]$()\\|]/', '\\\\\\0', $path);
    // $path = preg_replace("/{([a-zA-Z0-9_]+)}/",'(?<\\1>[^\\\\/]+)', $path); // named arguments. It's not worth the trouble
    $path = preg_replace("/\\{}/",'([^\\\\/]+)', $path??'');
    if(!$path)
      throw new \Exception('Preprocessing path failed');
    $path = "/^$path\/?$/sm";
    $this->routes[$path] = $handler;
  }

  public function lookup(?string $path=null) : ?RouteMatch {
    if($path === null)
      $path = preg_replace('/(\\?.*)?/sm', '', $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '');
    foreach($this->routes as $route => $callback)
      if(preg_match($route, $path, $matches))
        return new RouteMatch($callback, array_map('rawurldecode', array_slice($matches, 1)));
    return null;
  }

  public function route(?string $path=null) : bool {
    $route = $this->lookup($path);
    if($route){
      $route->execute();
      return true;
    }else{
      http_response_code(404);
      return false;
    }
  }

}

class f {

  public static function escapeURL(string $uri, array $components) : ?string {
    $bad = false;
    $i = 0;
    $ret = preg_replace_callback('/\\{([^}]*)}/', function(array $match)use(&$i,&$bad,$components){
      if($match[1]){
        $key = $match[1];
      }else{
        $key = $i++;
      }
      $result = $components[$key] ?? null;
      if(!is_string($result) && !is_int($result)){
        $bad = true;
        return '<inv>';
      }
      return rawurlencode("$result");
    }, $uri);
    if($bad){
      trigger_error("Failed to encode URI: $ret");
      return null;
    }
    return $ret;
  }

  public static function normalizeURL(string $url) : ?string {
    if(!preg_match('/^([^:\\/]+:\\/\\/[^\\/]+)(\\/[^\\?]*)?(\\?.*)?$/sm', $url, $match))
      return null;
    $result = strtolower($match[1]);
    if(@$match[2])
      $result .= preg_replace('/\\/+/', '/', $match[2]);
    $result .= $match[3] ?? '';
    return $result;
  }

};
