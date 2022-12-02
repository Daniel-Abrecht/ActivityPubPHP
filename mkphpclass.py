#!/usr/bin/env python3
import re
import sys
import json
import textwrap
from pathlib import Path
from rdflib import RDF, Graph, URIRef

rdf_domain = URIRef('http://www.w3.org/2000/01/rdf-schema#domain')
owl_Class = URIRef("http://www.w3.org/2002/07/owl#Class")
owl_DatatypeProperty = URIRef("http://www.w3.org/2002/07/owl#DatatypeProperty")
owl_ObjectProperty = URIRef("http://www.w3.org/2002/07/owl#ObjectProperty")
owl_FunctionalProperty = URIRef("http://www.w3.org/2002/07/owl#FunctionalProperty")
owl_Datatype = URIRef("http://www.w3.org/2000/01/rdf-schema#Datatype")
owl_properties = [owl_DatatypeProperty,owl_ObjectProperty,owl_FunctionalProperty]

wrapper = textwrap.TextWrapper(width=80-5)

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
      return self.module
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
  def getNamespace(self):
    parts = re.match('^([^:]*:(//)?)([^#]*)(#(.*))?$', self.uri)
    if not parts:
      return 'auto\\anonymous'
    return 'auto\\'+escape_id(parts[3].replace('/','\\'))
  def serialize(self):
    path = self.getNamespace().replace('\\','/');
    for v in self.classes.values():
      s = v.serialize()
      Path(path).mkdir(parents=True, exist_ok=True)
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
      self.kind = 'union';
      for t in getRdfObjectList(self.g, value):
        self.implements.append(self.module.registry.getOrCreateClass(t))
    elif key == URIRef('http://www.w3.org/2002/07/owl#intersectionOf'):
      self.kind = 'intersection';
      for t in getRdfObjectList(self.g, value):
        self.implements.append(self.module.registry.getOrCreateClass(t))
    elif key == URIRef('http://www.w3.org/2002/07/owl#complementOf'):
      self.kind = 'complement';
      for t in getRdfObjectList(self.g, value):
        self.implements.append(self.module.registry.getOrCreateClass(t))
  def getAbsNS(self, t='I'):
    return '\\'+self.getNamespace()+'\\'+t+'_'+self.name
  def getConstituents(self):
    res = []
    if   self.kind == 'class':
      res += [self]
    elif self.kind == 'union':
      for t in self.implements:
        res += t.getConstituents()
    return res
  def genTypeConstraint(self):
    parts = [t.getAbsNS() for t in self.getConstituents()]
    return parts
  def serialize(self):
    s = '<?php\n\n'
    s += 'declare(strict_types = 1);\n'
    s += 'namespace '+self.getNamespace()+';\n\n'
    if self.comment:
      s += '/**\n' + ''.join([f" * {s}\n" for s in wrapper.wrap(text='\n'.join(self.comment))]) + ' */\n'
    s += 'interface I_'+self.name+' extends \\auto\\POJO'
    if len(self.implements):
      s += ', '
      s += ', '.join([cls.getAbsNS() for cls in self.implements])
    s += ' {\n'
    s += '  const NS = '+json.dumps(self.module.context)+';\n'
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
        s += ' : ' + t;
      s += ';\n'
      s += '  public function set_'+name+'('+tv+' $value);\n'
      if property.isArray():
        s += '  public function add_'+name+'('+tv+' $value);\n';
        s += '  public function del_'+name+'('+tv+' $value);\n';
      s += '\n'
    s += '}\n\n'
    s += 'class C_'+self.name+' implements I_'+self.name+' {\n'
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
      s += '  public function set_'+name+'('+tv+' $value){ $this->var_'+name+' = ';
      if property.isArray():
        s += 'array_flatten($value)';
      else:
        s += '$value';
      s += '; }\n'
      if property.isArray():
        s += '  public function add_'+name+'('+tv+' $value){ $this->var_'+name+' = array_merge($this->var_'+name+', array_flatten($value)); }\n';
        s += '  public function del_'+name+'('+tv+' $value){ $this->var_'+name+' = array_diff($this->var_'+name+', array_flatten($value)); }\n';
      s += '\n'
    s += """\
  public function toArray() : array { return \\auto\\toArrayHelper($this); }
  public function fromArray(array $data) : void { \\auto\\fromArrayHelper($this, $data); }
  public function serialize() : string { return json_encode($this->toArray(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); }
  public function unserialize(string $data) : void { $this->fromArray(json_decode($data,true)); }
"""
    s += '}\n'
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
      parts.append('null')
    res = '|'.join(parts)
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
    r.getOrCreateModule(ns, context)
  for s, p, o in g.triples((None, RDF.type, owl_Class)):
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
