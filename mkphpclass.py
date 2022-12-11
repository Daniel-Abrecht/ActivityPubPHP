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

wrapper = textwrap.TextWrapper(width=80-5)

native_types = {
  "http://www.w3.org/2001/XMLSchema#string": ["string",None,None],
  "http://www.w3.org/2001/XMLSchema#normalizedString": ["string",None,None],
  "http://www.w3.org/2001/XMLSchema#decimal": ["float",None,None],
  "http://www.w3.org/2001/XMLSchema#float": ["float",None,None],
  "http://www.w3.org/2001/XMLSchema#double": ["float",None,None],
  "http://www.w3.org/2001/XMLSchema#integer": ["int",None,None],
  "http://www.w3.org/2001/XMLSchema#boolean": ["bool",None,None],
  "http://www.w3.org/2001/XMLSchema#base64Binary": ["string",None,None],
  "http://www.w3.org/2001/XMLSchema#hexBinary": ["string",None,None],
  "http://www.w3.org/2001/XMLSchema#nonPositiveInteger": ["int", "\\auto\\xsd\\nonPositiveInteger",None],
  "http://www.w3.org/2001/XMLSchema#negativeInteger": ["int", "\\auto\\xsd\\negativeInteger",None],
  "http://www.w3.org/2001/XMLSchema#long": ["int", "\\auto\\xsd\\long",None],
  "http://www.w3.org/2001/XMLSchema#int": ["int", "\\auto\\xsd\\int",None],
  "http://www.w3.org/2001/XMLSchema#short": ["int", "\\auto\\xsd\\short",None],
  "http://www.w3.org/2001/XMLSchema#byte": ["int", "\\auto\\xsd\\byte",None],
  "http://www.w3.org/2001/XMLSchema#nonNegativeInteger": ["int", "\\auto\\xsd\\nonNegativeInteger",None],
  "http://www.w3.org/2001/XMLSchema#unsignedLong": ["int", "\\auto\\xsd\\unsignedLong",None],
  "http://www.w3.org/2001/XMLSchema#unsignedInt": ["int", "\\auto\\xsd\\unsignedInt",None],
  "http://www.w3.org/2001/XMLSchema#unsignedShort": ["int", "\\auto\\xsd\\unsignedShort",None],
  "http://www.w3.org/2001/XMLSchema#unsignedByte": ["int", "\\auto\\xsd\\unsignedByte",None],
  "http://www.w3.org/2001/XMLSchema#positiveInteger": ["int", "\\auto\\xsd\\positiveInteger",None],
  "http://www.w3.org/2001/XMLSchema#dateTime": ["\\DateTimeInterface", None, "xsd.php"],
  "http://www.w3.org/2001/XMLSchema#anyURI": ["\\auto\\xsd\\I_anyURI", None, "xsd.php"],
  "http://www.w3.org/ns/activitystreams#Link": ["\\auto\\www_w3_org\\ns\\activitystreams\\I_Link", None, "activitypub.php"],
}

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
  def getModuleForURI(self, uri):
    l = -1
    m = None
    for k,v in self.module.items():
      if l < len(k) and uri.startswith(k):
        m = v
        l = len(k)
    return m
  def getName(uri):
    m = self.getModuleForURI(uri)
    if not m:
      return None
    return [m.uri, uri[len(m.uri):]]
  def serialize(self):
    for m in self.module.values():
      m.serialize()

class Module:
  def __init__(self, registry, uri, context):
    self.uri = uri
    self.context = context or uri
    self.registry = registry
    self.g = registry.g
    self.classes = {}
    self.typefield = None
  def getNamespace(self):
    parts = re.match('^([^:]*:(//)?)([^#]*)(#(.*))?$', self.uri)
    if not parts:
      return 'auto\\anonymous'
    return 'auto\\'+escape_id(parts[3].replace('/','\\'))
  def serialize(self):
    if len(self.classes) == 0:
      return;
    path = self.getNamespace().replace('\\','/')
    Path(path).mkdir(parents=True, exist_ok=True)
    with open(path+'/__module__.php', 'w') as f:
      s = """\
<?php

declare(strict_types = 1);
namespace """+self.getNamespace()+""";

class __module__ {
  const META = [
    "ID" => """+json.dumps(self.context)+""",
    "PREFIX" => """+json.dumps(self.uri)+""",
"""
      if self.typefield:
        s += '"TYPEFIELD" => ' + json.dumps(self.typefield) + ",\n"
      s += """\
  ];
}
"""
      print(s, file=f)
    for v in self.classes.values():
      s = v.serialize()
      if not s:
        continue
      with open(path+'/'+v.name+'.php', 'w') as f:
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
    self.kind = 'class'
    self.module = module
    self.g = module.g
    self.uri = uri
    self.comment = []
    self.implements = []
    self.property = {}
    self.label = None
    if self.uri.startswith(self.module.uri):
      self.name = self.uri[len(self.module.uri):]
    else:
      self.name = f'Anonymous{self.i}'
  def getOrCreateProperty(self, uri):
    if uri in self.property:
      return self.property[uri]
    p = Property(self, uri)
    self.property[p.name] = p
    return p
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
    return '\\'+self.getNamespace()+'\\'+t+'_'+self.name
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
    nt = native_types.get(str(self.uri)) or [None, None, None]
    if nt[0] and not nt[2]:
      return
    s = '<?php\n\n'
    s += 'declare(strict_types = 1);\n'
    s += 'namespace '+self.getNamespace()+';\n\n'
    if self.comment:
      s += '/**\n' + ''.join([f" * {s}\n" for s in wrapper.wrap(text='\n'.join(self.comment))]) + ' */\n'
    if nt[2]:
      s += 'interface D_'
    else:
      s += 'interface I_'
    s += self.name+' extends \\auto\\POJO'
    if len(self.implements):
      s += ', '
      s += ', '.join([cls.getAbsNS() for cls in self.implements])
    s += ' {\n'
    s += '  const NS = \\'+self.module.getNamespace()+'\\__module__::META;\n'
    s += '  const TYPE = '+json.dumps(self.name)+';\n'
    s += '  const IRI = '+json.dumps(self.uri)+';\n\n'
    for name, property in self.property.items():
      if property.comment:
        s += '  /**\n' + ''.join([f"   * {s}\n" for s in wrapper.wrap(text='\n'.join(property.comment))]) + '   */\n'
      t  = ''
      tv = ''
      if property.type:
        t  = property.genTypeConstraint()
        tv = property.genTypeConstraint(True)
      s += '  #[\\auto\\Property('+json.dumps(property.uri)+','+json.dumps(property.name)+')]\n'
      s += '  public function get_'+name+'()'
      if t:
        s += ' : ' + t
      s += ';\n'
      s += '  public function set_'+name+'('+tv+' $value) : void;\n'
      if property.isArray():
        s += '  public function add_'+name+'('+tv+' $value) : void;\n'
        s += '  public function del_'+name+'('+tv+' $value) : void;\n'
      s += '\n'
    s += '}\n\n'
    if nt[2]:
      s += 'abstract class A_' + self.name + ' implements D_' + self.name + ' {\n'
    else:
      s += 'class C_' + self.name + ' implements I_' + self.name + ' {\n'
    for name, property in self.getAllProperties().items():
      t  = ''
      tv = ''
      if property.type:
        t  = property.genTypeConstraint()
        tv = property.genTypeConstraint(True)
      s += '  private '+t+' $var_'+name
      if   property.isArray():
        s += ' = []'
      elif property.nullable or not property.type:
        s += ' = null'
      s += ';\n'
      s += '  public function get_'+name+'()'
      if t:
        s += ' : ' + t + ' '
      s += '{ return $this->var_'+name+'; }\n'
      s += '  public function set_'+name+'('+tv+' $value) : void { $this->var_'+name+' = '
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
        s += '  public function add_'+name+'('+tv+' $value) : void { $this->var_'+name+' = array_merge($this->var_'+name+', \\auto\\array_flatten($value,'+ser+')); }\n'
        s += '  public function del_'+name+'('+tv+' $value) : void { $this->var_'+name+' = array_diff ($this->var_'+name+', \\auto\\array_flatten($value,'+ser+')); }\n'
      s += '\n'
    s += """\
  public function toArray($oldns=null) : """+('string|null|' if nt[2] else '')+"""array { return \\auto\\toArrayHelper($this,$oldns); }
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

class Property:
  def __init__(self, cls, uri):
    self.parent = cls
    self.g = cls.g
    self.uri = uri
    self.type = None
    self.comment = []
    self.nullable = True
    self.objectproperty = False
    self.datatypeproperty = False
    self.functionalproperty = False
    if self.uri.startswith(self.parent.module.uri):
      self.name = self.uri[len(self.parent.module.uri):]
    else:
      self.name = f'Anonymous{self.i}'
  def setMeta(self, key, value):
    if key == URIRef('http://www.w3.org/2000/01/rdf-schema#range'):
      self.type = self.parent.module.registry.getOrCreateClass(value)
    elif key == URIRef('http://www.w3.org/2000/01/rdf-schema#comment'):
      self.comment.append(value)
    elif key == 'kind':
      if   value == owl_DatatypeProperty:
        self.datatypeproperty = True
      elif value == owl_FunctionalProperty:
        self.functionalproperty = True
      elif value == owl_ObjectProperty:
        self.objectproperty = True
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

def getTypeOfProperty(r,g,s,p):
  for s, p, o in g.triples((s, p, None)):
    m = r.getModuleForURI(s)
    if o in m.classes:
      return m.classes[o]

def createModule(files):
  g = Graph()
  for f in files:
    g.parse(f, format='turtle')
  r = Registry(g)
  for prefix, ns in g.namespaces():
    context = None
    for s, p, o in g.triples((ns, URIRef('http://dpa.li/ns/owl/fixes/meta#context'), None)):
      context = o
    m = r.getOrCreateModule(ns, context)
    for s, p, o in g.triples((ns, URIRef('http://dpa.li/ns/owl/fixes/meta#typefield'), None)):
      m.typefield = o
  for s, p, o in chain(g.triples((None, RDF.type, owl_Class)),
                       g.triples((None, RDF.type, rdfs_Class))):
    ci = r.getOrCreateClass(s)
    for s, p, o in g.triples((s, None, None)):
      ci.setMeta(p, o)
  for pt in owl_properties:
    for s, p, o in g.triples((None, RDF.type, pt)):
      t = getTypeOfProperty(r, g, s, rdf_domain)
      if not t:
        continue
      for t in t.getTypes():
        property = t.getOrCreateProperty(s)
        property.setMeta('kind', pt)
        for s, p, o in g.triples((s, None, None)):
          property.setMeta(p, o)
  r.serialize()

createModule(sys.argv[1:])
