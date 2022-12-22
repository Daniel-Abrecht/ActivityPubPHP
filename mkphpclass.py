#!/usr/bin/env python3
import re
import sys
import json
import textwrap
from pathlib import Path
from rdflib import RDF, Graph, URIRef
from itertools import chain

rdf_domain = URIRef('http://www.w3.org/2000/01/rdf-schema#domain')
owl_Class = URIRef("http://www.w3.org/2002/07/owl#Class")
rdfs_Class = URIRef("http://www.w3.org/2000/01/rdf-schema#Class")
owl_DatatypeProperty = URIRef("http://www.w3.org/2002/07/owl#DatatypeProperty")
owl_ObjectProperty = URIRef("http://www.w3.org/2002/07/owl#ObjectProperty")
owl_FunctionalProperty = URIRef("http://www.w3.org/2002/07/owl#FunctionalProperty")
owl_Datatype = URIRef("http://www.w3.org/2000/01/rdf-schema#Datatype")
owl_properties = [owl_DatatypeProperty,owl_ObjectProperty,owl_FunctionalProperty]
owl_equivalentProperty = URIRef("http://www.w3.org/2002/07/owl#equivalentProperty")
owl_sameAs = URIRef("http://www.w3.org/2002/07/owl#sameAs")

wrapper = textwrap.TextWrapper(width=80-5)

with open("auto/override_meta.json",'r') as f:
  native_types = json.load(f)

def escape_id(s):
  s = re.sub('[^a-zA-Z0-9_\x7f-\xff\\\\]', '_', s)
  s = re.sub('([/\\\\])([0-9])', '\\1_\\2', s)
  return s

class Registry:
  def __init__(self, g):
    self.g = g
    self.module = { '': Module(self, '', None) }
  def getOrCreateModule(self, ns, context):
    if ns in self.module:
      return self.module[ns]
    m = Module(self, ns, context)
    self.module[ns] = m
    return m
  def getOrCreateClass(self, uri):
    m = self.getModuleForURI(uri)
    if uri in m.classes:
      return m.classes[uri]
    cls = Class(m, uri)
    m.classes[uri] = cls
    return cls
  def getOrCreateProperty(self, uri):
    m = self.getModuleForURI(uri)
    if uri in m.property:
      return m.property[uri]
    p = Property(m, uri)
    m.property[uri] = p
    return p
  def getModuleForURI(self, uri):
    l = -1
    m = None
    for k,v in self.module.items():
      if l < len(k) and uri.startswith(k):
        m = v
        l = len(k)
    return m
  def getName(self,uri):
    m = self.getModuleForURI(uri)
    if not m:
      return None
    return [str(m.uri), uri[len(m.uri):]]
  def serialize(self):
    for m in self.module.values():
      m.serialize()

class Module:
  def __init__(self, registry, uri, context):
    self.uri = uri
    self.context = context
    self.registry = registry
    self.g = registry.g
    self.classes = {}
    self.property = {}
    self.ldmap = {}
  def getNamespace(self):
    parts = re.match('^([^:]*:(//)?)([^#]*)(#(.*))?$', self.uri)
    if not parts:
      return 'auto\\anonymous'
    return 'auto\\'+escape_id(parts[3].replace('/','\\'))
  def getContextNamespace(self):
    parts = re.match('^([^:]*:(//)?)([^#]*)(#(.*))?$', self.context or self.uri)
    if not parts:
      return 'auto\\anonymous'
    return 'auto\\'+escape_id(parts[3].replace('/','\\'))
  def setMapping(self, prefix, value):
    self.ldmap[str(prefix)] = str(value)
  def serialize(self):
    if len(self.classes) == 0:
      return;
    path = self.getNamespace().replace('\\','/')
    Path(path).mkdir(parents=True, exist_ok=True)
    contextPath = self.getContextNamespace().replace('\\','/')
    Path(contextPath).mkdir(parents=True, exist_ok=True)
    with open(contextPath+'/__module__.mod', 'w') as f:
      ldmap_n = {}
      ldmap_c = {}
      ldmap_p = {}
      ldmap_a = {}
      for prefix, value in self.ldmap.items():
        ldmap_n[prefix] = value
      for cls in self.classes.values():
        ldmap_c[cls.name] = cls.uri
      for prop in self.property.values():
        if prop.name != str(prop.uri):
          ldmap_p[prop.name] = prop.uri
        for uri in prop.uris:
          if prop.uri != uri:
            uprefix, uname = self.registry.getName(uri)
            ldmap_p[uname] = prop.uri
            ldmap_a[uri] = prop.uri
      ldmap = dict(
          sorted(ldmap_n.items())
        + sorted(ldmap_c.items())
        + sorted(ldmap_p.items())
        + sorted(ldmap_a.items())
      )
      s = """\
<?php

declare(strict_types = 1);
namespace """+self.getContextNamespace()+""";

class __module__ {
  const META = [
    "CONTEXT" => """+json.dumps(self.context)+""",
    "PREFIX" => """+json.dumps(self.uri)+""",
"""
      s += """\
    "MAPPING" => [
"""
      for prefix, value in ldmap.items():
        s += '      ' + json.dumps(prefix) + " => " + json.dumps(value) + ',\n'
      s += """\
    ]
  ];
}
"""
      print(s, file=f)
    for v in self.classes.values():
      s = v.serialize()
      if not s:
        continue
      with open(path+'/'+escape_id(v.name)+'.mod', 'w') as f:
        print(s, file=f)

def getRdfObjectList(g, first):
  n = first
  while n and n != URIRef('http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'):
    c = n
    n = None
    for s, p, o in g.triples((c, None, None)):
      if p == URIRef('http://www.w3.org/1999/02/22-rdf-syntax-ns#rest'):
        n = o
      elif p == URIRef('http://www.w3.org/1999/02/22-rdf-syntax-ns#first'):
        yield o

class Class:
  def __init__(self, module, uri):
    Class.i += 1
    self.i = Class.i
    self.kind = 'class'
    self.module = module
    self.g = module.g
    self.uri = uri
    self.comment = []
    self.implements = []
    self.property = {}
    self.label = None
    self.extra_context = set()
    if self.uri.startswith(self.module.uri):
      self.name = self.uri[len(self.module.uri):]
    else:
      self.name = f'Anonymous{self.i}'
    if self.name == '@id':
      raise Exception("Huh")
  def addProperty(self, iri, property):
    if property.module.context:
      self.extra_context.add(str(property.module.context))
    self.property[iri] = property
  def getNamespace(self):
    return self.module.getNamespace()
  def setMeta(self, key, value):
    if   key == URIRef('http://www.w3.org/2000/01/rdf-schema#label'):
      self.label = value
    elif key == URIRef('http://www.w3.org/2000/01/rdf-schema#comment'):
      self.comment.append(value)
    elif key == URIRef('http://www.w3.org/2000/01/rdf-schema#subClassOf'):
      self.implements.append(self.module.registry.getOrCreateClass(value))
    elif key == URIRef('http://www.w3.org/2002/07/owl#unionOf'):
      self.kind = 'union'
      for t in getRdfObjectList(self.g, value):
        self.implements.append(self.module.registry.getOrCreateClass(t))
    elif key == URIRef('http://www.w3.org/2002/07/owl#intersectionOf'):
      self.kind = 'intersection'
      for t in getRdfObjectList(self.g, value):
        self.implements.append(self.module.registry.getOrCreateClass(t))
    elif key == URIRef('http://www.w3.org/2002/07/owl#complementOf'):
      self.kind = 'complement'
      for t in getRdfObjectList(self.g, value):
        self.implements.append(self.module.registry.getOrCreateClass(t))
  def getAbsNS(self, t='I'):
    if t == 'I' and str(self.uri) in native_types and native_types[str(self.uri)][0]:
      return native_types[str(self.uri)][0]
    return '\\'+self.getNamespace()+'\\'+t+'_'+escape_id(self.name)
  def getConstituents(self):
    res = set()
    if   self.kind == 'class':
      res.add(self)
      if str(self.uri) in native_types and native_types[str(self.uri)][2]:
        res.add(self.module.registry.getOrCreateClass(URIRef('http://www.w3.org/2001/XMLSchema#string')))
    elif self.kind == 'union':
      for t in self.implements:
        res |= t.getConstituents()
    return res
  def getInstTypes(self):
    res = set()
    if   self.kind == 'class':
      if str(self.uri) in native_types and native_types[str(self.uri)][2]:
        res.add(self.getAbsNS('C'))
    elif self.kind == 'union':
      for t in self.implements:
        res |= t.getInstTypes()
    return res
  def genTypeConstraint(self):
    parts = set(t.getAbsNS() for t in self.getConstituents())
    return parts
  def serialize(self):
    extra_context = sorted(self.extra_context)
    if self.kind != 'class':
      return
    nt = native_types.get(str(self.uri)) or [None, None, None]
    if nt[0] and not nt[2]:
      return
    s = '<?php\n\n'
    s += 'declare(strict_types = 1);\n'
    s += 'namespace '+self.getNamespace()+';\n\n'
    if extra_context:
      s += f'const EC{self.i} = {json.dumps(extra_context)};\n\n'
    if self.comment:
      s += '/**\n' + ''.join([f" * {s}\n" for s in wrapper.wrap(text='\n'.join(self.comment))]) + ' */\n'
    if nt[2]:
      s += 'interface D_'
    else:
      s += 'interface I_'
    s += escape_id(self.name)+' extends \\auto\\POJO'
    if len(self.implements):
      s += ', '
      s += ', '.join([cls.getAbsNS() for cls in self.implements])
    s += ' {\n'
    s += '  const NS = \\'+self.module.getContextNamespace()+'\\__module__::META;\n'
    s += '  const IRI = '+json.dumps(self.uri)+';\n\n'
    for piri, property in self.property.items():
      pprefix, pname = self.module.registry.getName(piri)
      pname = escape_id(pname)
      if property.comment:
        s += '  /**\n' + ''.join([f"   * {s}\n" for s in wrapper.wrap(text='\n'.join(property.comment))]) + '   */\n'
      t  = ''
      tv = ''
      if property.type:
        t  = property.genTypeConstraint()
        tv = property.genTypeConstraint(True)
      st = property.getType()
      pp = json.dumps(property.uri)
      pp += ',' + json.dumps(st.uri if st else None)
      pp += f',EC{self.i}[{extra_context.index(str(property.module.context))}]' if property.module.context else ',null'
      s += '  #[\\auto\\Property('+pp+')]\n'
      s += '  public function get_'+pname+'()'
      if t:
        s += ' : ' + t
      s += ';\n'
      s += '  public function set_'+pname+'('+tv+' $value) : void;\n'
      if property.isArray():
        s += '  public function add_'+pname+'('+tv+' $value) : void;\n'
        s += '  public function del_'+pname+'('+tv+' $value) : void;\n'
      s += '\n'
    s += '}\n\n'
    if nt[2]:
      s += 'abstract class A_' + escape_id(self.name) + ' implements D_' + escape_id(self.name) + ' {\n'
    else:
      s += 'class C_' + escape_id(self.name) + ' implements I_' + escape_id(self.name) + ' {\n'
    for piri, property in self.getAllProperties().items():
      pprefix, pname = self.module.registry.getName(piri)
      pname = escape_id(pname)
      cname = escape_id(property.name)
      t  = ''
      tv = ''
      if property.type:
        t  = property.genTypeConstraint()
        tv = property.genTypeConstraint(True)
      if piri == property.uri:
        s += '  private '+t+' $var_'+cname
        if   property.isArray():
          s += ' = []'
        elif property.nullable or not property.type:
          s += ' = null'
        s += ';\n'
      s += '  public function get_'+pname+'()'
      if t:
        s += ' : ' + t + ' '
      s += '{ return $this->var_'+cname+'; }\n'
      s += '  public function set_'+pname+'('+tv+' $value) : void { $this->var_'+cname+' = '
      types = []
      if property.type:
        types = sorted([*property.type.getInstTypes()])
      ser = json.dumps(types)
      if property.isArray():
        s += '\\auto\\array_flatten($value,'+ser+')'
      else:
        if len(types):
          s += '\\auto\\deser($value,'+ser+')'
        else:
          s += '$value'
      s += '; }\n'
      if property.isArray():
        s += '  public function add_'+pname+'('+tv+' $value) : void { $this->var_'+cname+' = array_merge($this->var_'+cname+', \\auto\\array_flatten($value,'+ser+')); }\n'
        s += '  public function del_'+pname+'('+tv+' $value) : void { $this->var_'+cname+' = array_diff ($this->var_'+cname+', \\auto\\array_flatten($value,'+ser+')); }\n'
      s += '\n'
    s += """\
  public function toArray(\\auto\\ContextHelper $context=null) : """+('string|null|' if nt[2] else '')+"""array { return \\auto\\toArrayHelper($this,$context); }
  public function fromArray(array|string $data) : void { \\auto\\fromArrayHelper($this, $data); }
  public function serialize() : string { return json_encode($this->toArray(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); }
  public function unserialize(string $data) : void { $this->fromArray(json_decode($data,true)); }
"""
    s += '}\n'
    if nt[2]:
      s += '\\auto\\load(' + json.dumps(nt[2]) + ');';
    return s
  def getAllProperties(self):
    properties = {}
    for parent in self.implements:
      properties |= parent.getAllProperties()
    properties |= self.property
    return properties
  def getTypes(self):
    return [self]
Class.i = 0

class Property:
  def __init__(self, module, uri):
    Property.i += 1
    self.i = Property.i
    self.module = module
    self.g = module.g
    self.classes = set()
    self.uri = uri
    self.uris = {self.uri}
    self.type = None
    self.comment = []
    self.nullable = True
    self.objectproperty = False
    self.datatypeproperty = False
    self.functionalproperty = False
    self.prefix, self.name = self.module.registry.getName(self.uri)
  def setMeta(self, key, value, alias):
    if key == URIRef('http://www.w3.org/2000/01/rdf-schema#range'):
      self.type = self.module.registry.getOrCreateClass(value)
    elif key == URIRef('http://www.w3.org/2000/01/rdf-schema#comment'):
      self.comment.append(value)
    elif key == rdf_domain:
      if str(value) == '*':
        for s, p, o in chain(self.g.triples((None, RDF.type, owl_Class)),
                             self.g.triples((None, RDF.type, rdfs_Class))):
          self.setMeta(key, s, alias)
      else:
        cls = self.module.registry.getOrCreateClass(value)
        for cls in cls.getConstituents():
          self.classes.add(cls)
          cls.addProperty(alias or self.uri, self)
    elif key == 'kind':
      if   value == owl_DatatypeProperty:
        self.datatypeproperty = True
      elif value == owl_FunctionalProperty:
        self.functionalproperty = True
      elif value == owl_ObjectProperty:
        self.objectproperty = True
  def sameAs(self, value):
    if value in self.module.property:
      prev = self.module.property[value]
      self.module.property[self.uri] = prev
      prev.uris.add(self.uri)
    else:
      self.module.property[value] = self
      self.uri = value
      self.prefix, self.name = self.module.registry.getName(self.uri)
      self.uris.add(value)
  def genTypeConstraint(self, varadic=False):
    if not self.type:
      return None
    parts = self.type.genTypeConstraint()
    if self.nullable:
      parts.add('null')
    res = '|'.join(sorted([*parts]))
    if self.isArray():
      if varadic:
        res += '|array...'
      else:
        res = 'array/*['+res+']*/'
    return res
  def isArray(self):
    return not self.datatypeproperty
  # If it can be only 1 type, return it
  def getType(self):
    if self.type and self.type.kind == 'class':
      return self.type
    return None
Property.i = 1

def createModule(files):
  g = Graph()
  for f in files:
    g.parse(f, format='turtle')
  r = Registry(g)
  nss = set(ns for prefix, ns in g.namespaces())
  for ns in nss:
    context = None
    for s, p, o in g.triples((ns, URIRef('http://dpa.li/ns/owl/fixes/meta#context'), None)):
      context = o
    m = r.getOrCreateModule(ns, context)
    for s, p, o in g.triples((ns, URIRef('http://dpa.li/ns/owl/fixes/meta#ldmap'), None)):
      for s, p, o in g.triples((o, None, None)):
        m.setMapping(p,o)
  for s, p, o in chain(g.triples((None, RDF.type, owl_Class)),
                       g.triples((None, RDF.type, rdfs_Class))):
    ci = r.getOrCreateClass(s)
    for s, p, o in g.triples((s, None, None)):
      ci.setMeta(p, o)
  for pt in owl_properties:
    for s, p, o in g.triples((None, RDF.type, pt)):
      for s, p, o in chain(g.triples((s, owl_equivalentProperty, None)),
                           g.triples((s, owl_sameAs, None))):
        property = r.getOrCreateProperty(s)
        property.sameAs(o)
      property = r.getOrCreateProperty(s)
      property.setMeta('kind', pt, s)
      for s, p, o in chain(g.triples((s, None, None))):
        property.setMeta(p, o, s)
  r.serialize()

createModule(sys.argv[1:])
