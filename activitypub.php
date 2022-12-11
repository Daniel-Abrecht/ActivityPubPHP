<?php
declare(strict_types = 1);

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
            if(count($value) != 1)
              return parent::toArray($oldns);
          }else if($value === null || (is_array($value) && count($value) == 0)){
            return parent::toArray($oldns);
          }
        }
      }
      return $this->toString();
    }
    public function toString() : string|null {
      $v = @$this->get_href()[0];
      if($v)
        return $v->toString();
      return null;
    }
  }
}
