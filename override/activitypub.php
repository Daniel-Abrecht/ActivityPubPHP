<?php
declare(strict_types = 1);
/* override: {
  "https://www.w3.org/ns/activitystreams#Link": ["\\auto\\www_w3_org\\ns\\activitystreams\\I_Link", null, "override/activitypub.php"]
}
*/


namespace auto\www_w3_org\ns\activitystreams {
  interface I_Link extends D_Link, \auto\simple_type {}

  class C_Link extends A_Link implements I_Link {
    public function __construct(string $scalar=null){
      if($scalar !== null)
        $this->set_href($scalar);
    }
    public function toArray($oldns=null) : array|string|null {
      foreach(\auto\getAllParents(get_class($this)) as $reflection){
        $info = [];
        foreach($reflection->getMethods() as $entry){
          if(!($attr = @$entry->getAttributes(Property::class)[0]))
            continue;
          if(!($name = @explode('_', $entry->getName(), 2)[1]))
            continue;
          $value = $o->{'get_'.$name}();
          if($name == 'href'){
            if($value !== null)
              return parent::toArray($oldns);
          }else if($value === null || (is_array($value) && count($value) == 0)){
            return parent::toArray($oldns);
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
