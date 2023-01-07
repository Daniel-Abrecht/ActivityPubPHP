<?php
declare(strict_types = 1);
/* override: {
  "https://www.w3.org/ns/activitystreams#Link": ["\\dpa\\pojo\\www_w3_org\\ns\\activitystreams\\I_Link", null, "lib/override/activitypub.php"]
}
*/


namespace dpa\pojo\www_w3_org\ns\activitystreams {
  interface I_Link extends D_Link {}

  class C_Link extends A_Link implements I_Link, \dpa\jsonld\simple_type {
    public function __construct(string $scalar=null){
      if($scalar !== null)
        $this->set_href($scalar);
    }
    public function toArray(\dpa\jsonld\ContextHelper $context=null) : array|string|null {
      foreach(\dpa\jsonld\f::getAllParents(get_class($this)) as $reflection){
        foreach($reflection->getMethods() as $entry){
          if(!($attr = @$entry->getAttributes(\dpa\jsonld\Property::class)[0]))
            continue;
          if(!($name = @explode('_', $entry->getName(), 2)[1]))
            continue;
          $value = $this->{'get_'.$name}();
          if($name === 'href'){
            if($value === null){
              return parent::toArray($context);
            }
          }else if($value !== null && !(is_array($value) && count($value) == 0)){
            return parent::toArray($context);
          }
        }
      }
      $v = $this->get_href();
      if($v)
        return $v->__toString();
      return null;
    }
    public function __toString() : string {
      $v = $this->get_href();
      if($v)
        return $v->__toString();
      return '';
    }
  }
}
