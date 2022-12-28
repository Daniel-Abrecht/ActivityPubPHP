#!/usr/bin/env python3
import os
import re
import sys
import json
import textwrap
from glob import glob
from pathlib import Path
from rdflib import RDF, Graph, URIRef, Literal
from itertools import chain

rdf_domain = URIRef('http://www.w3.org/2000/01/rdf-schema#domain')
owl_Class = URIRef("http://www.w3.org/2002/07/owl#Class")
rdfs_Class = URIRef("http://www.w3.org/2000/01/rdf-schema#Class")
rdfs_Datatype = URIRef("http://www.w3.org/2000/01/rdf-schema#Datatype")
owl_Ontology = URIRef("http://www.w3.org/2002/07/owl#Ontology")
owl_DatatypeProperty = URIRef("http://www.w3.org/2002/07/owl#DatatypeProperty")
owl_ObjectProperty = URIRef("http://www.w3.org/2002/07/owl#ObjectProperty")
owl_FunctionalProperty = URIRef("http://www.w3.org/2002/07/owl#FunctionalProperty")
owl_onDatatype = URIRef("http://www.w3.org/2002/07/owl#onDatatype")
owl_properties = [owl_DatatypeProperty,owl_ObjectProperty,owl_FunctionalProperty]
owl_equivalentProperty = URIRef("http://www.w3.org/2002/07/owl#equivalentProperty")
owl_sameAs = URIRef("http://www.w3.org/2002/07/owl#sameAs")
dpa_context = URIRef('http://dpa.li/ns/owl/fixes/meta#context')

wrapper = textwrap.TextWrapper(width=80-5)

with open("auto/override_meta.json",'r') as f:
  native_types = json.load(f)

def escape_id(s):
  s = re.sub('[^a-zA-Z0-9_\x7f-\xff\\\\/]', '_', s)
  s = re.sub('([/\\\\]|^)([0-9])', '\\1_\\2', s)
  s = re.sub('([/\\\\])+', '\\1', s)
  return s

def split_uri(uri, t=None, rla=False):
  parts = re.split('[/\\\\#?=]+', uri)
  if len(parts) == 1:
    parts.insert(0, 'anonymous')
  if parts[0][-1] == ':':
    parts = parts[1:]
  if t:
    parts[-1] = t + '_' + parts[-1]
  if rla:
    parts = parts[:-1]
  parts.insert(0, 'auto')
  return [escape_id(part) for part in parts if part not in ['','.','..']]

def getName(uri):
  return split_uri(uri)[-1]

class Registry:
  def __init__(self, g):
    self.g = g
    self.context = {}
    self.classes = {}
    self.property = {}
  def getOrCreateClass(self, uri):
    if uri in self.classes:
      return self.classes[uri]
    cls = Class(self, uri)
    self.classes[uri] = cls
    return cls
  def getOrCreateProperty(self, uri):
    if uri in self.property:
      return self.property[uri]
    p = Property(self, uri)
    self.property[uri] = p
    return p
  def getOrCreateContext(self, uri):
    uri = URIRef(uri)
    if uri in self.context:
      return self.context[uri]
    c = Context(self, uri)
    self.context[uri] = c
    return c
  def serialize(self):
    for v in self.classes.values():
      v.serialize()
    for v in self.context.values():
      v.serialize()
  def info(self):
    in_ontology = {str(x) for x in chain(self.classes.keys(), self.property.keys()) if ':' in x}
    for context in self.context.values():
      print(f"Checking for context entries not in any ontology in context <{context.uri}>")
      for iri in sorted(set(context.fldmap.values())):
        if ':' not in iri:
          continue
        if str(iri) not in in_ontology:
          print('  '+str(iri))
    in_context = set()
    for y in self.context.values():
      for x in y.fldmap.values():
        if ':' in x:
          in_context.add(str(x))
    for cls in self.classes.values():
      missing = set()
      if not cls.context:
        continue
      if ':' in cls.uri and str(cls.uri) not in in_context:
        missing.add(str(cls.uri))
      for iri, prop in cls.property.items():
        if ':' in iri and str(iri) not in in_context:
          missing.add(str(iri))
      if missing:
        print(f"IRIs not in any context for class <{cls.uri}>");
        for x in sorted(missing):
          print('  '+x)

class Context:
  def __init__(self, registry, uri):
    self.registry = registry
    self.g = registry.g
    self.uri = uri
    self.ldmap = {}
    self.fldmap = {}
    self.ldext = {}
    with open('download/context/'+self.uri,'r') as f:
      context = json.load(f)['@context']
      if not isinstance(context, list):
        context = [context]
      for ctx in context:
        if isinstance(ctx, str):
          mc = self.registry.getOrCreateContext(ctx)
          self.ldmap = {**self.ldmap, **mc.ldmap}
        if not isinstance(ctx, dict):
          continue
        for k,v in ctx.items():
          if isinstance(v, dict):
            v = v.get('@id')
          if isinstance(v, str) and k[0] != '@':
            self.ldmap[k] = v
    self.fldmap = self.ldmap
    for k,v in self.fldmap.items():
      self.fldmap[k] = self.expand(v)
    for s, p, o in self.g['*'].triples((self.uri, URIRef('http://dpa.li/ns/owl/fixes/meta#ldmap'), None)):
      for s, p, o in self.g['*'].triples((o, None, None)):
        self.ldext[p] = o
  def expand(self, key):
    while True:
      if key in self.fldmap:
        key = self.fldmap[key]
        continue
      x = [*key.split(':',1),None]
      prefix = x[0]
      ref = x[1]
      if ref is not None and prefix in self.fldmap:
        key = self.fldmap[prefix] + ref
        continue
      break
    return key
  def getAbsNS(self):
    return '\\'.join(split_uri(self.uri))
  def getDirPath(self):
    return '/'.join(split_uri(self.uri))
  def getAbsPath(self):
    return self.getDirPath() + '/__module__.mod'
  def serialize(self):
    ldext_c = {}
    ldext_p = {}
    ldext_a = {}
    for cls in self.registry.classes.values():
      if self.uri not in cls.context:
        continue
      ldext_c[cls.getName()] = cls.uri
    for prop in self.registry.property.values():
      if self.uri not in prop.context:
        continue
      ldext_p[prop.getName()] = prop.uri
      for uri in prop.uris:
        uname = getName(uri)
        ldext_p[uname] = prop.uri
        ldext_a[uri] = prop.uri
    ldext = dict(
        sorted(ldext_c.items())
      + sorted(ldext_p.items())
      + sorted(ldext_a.items())
    )
    ldext = dict((k,v) for k,v in ldext.items() if str(k) != str(v) and k not in self.ldmap)
    s = """\
<?php

declare(strict_types = 1);
namespace """+self.getAbsNS()+""";

class __module__ {
  const META = [
    "CONTEXT" => """+json.dumps(self.uri)+""",
"""
    s += """\
    "MAPPING" => [
"""
    for prefix, value in self.ldmap.items():
      s += '      ' + json.dumps(prefix) + " => " + json.dumps(value) + ',\n'
    s += """\
    ],
    "MAPPING_EXT" => [
"""
    for prefix, value in chain(self.ldext.items(), ldext.items()):
      s += '      ' + json.dumps(prefix) + " => " + json.dumps(value) + ',\n'
    s += """\
    ]
  ];
}
"""
    Path(self.getDirPath()).mkdir(parents=True, exist_ok=True)
    with open(self.getAbsPath(), 'w') as f:
      print(s, file=f)

def getRdfObjectList(g, first):
  n = first
  while n and n != URIRef('http://www.w3.org/1999/02/22-rdf-syntax-ns#nil'):
    c = n
    n = None
    for s, p, o in g['*'].triples((c, None, None)):
      if p == URIRef('http://www.w3.org/1999/02/22-rdf-syntax-ns#rest'):
        n = o
      elif p == URIRef('http://www.w3.org/1999/02/22-rdf-syntax-ns#first'):
        yield o

class Class:
  def __init__(self, registry, uri):
    Class.i += 1
    self.i = Class.i
    self.g = registry.g
    self.kind = 'unknown'
    self.context = set()
    self.registry = registry
    self.uri = uri
    self.comment = []
    self.implements = set()
    self.property = {}
    self.label = None
    if str(self.uri) in native_types:
      self.kind = 'class'
  def addProperty(self, iri, property):
    self.property[iri] = property
  def setMeta(self, key, value):
    if   key == URIRef('http://www.w3.org/2000/01/rdf-schema#label'):
      self.label = value
    elif key == URIRef('http://www.w3.org/2000/01/rdf-schema#comment'):
      self.comment.append(value)
    elif key == dpa_context:
      self.context.add(value)
    elif key == URIRef('http://www.w3.org/2000/01/rdf-schema#subClassOf'):
      self.implements.add(self.registry.getOrCreateClass(value))
    elif key == URIRef('http://www.w3.org/2002/07/owl#unionOf'):
      self.kind = 'union'
      for t in getRdfObjectList(self.g, value):
        self.implements.add(self.registry.getOrCreateClass(t))
    elif key == URIRef('http://www.w3.org/2002/07/owl#intersectionOf'):
      self.kind = 'intersection'
      for t in getRdfObjectList(self.g, value):
        self.implements.add(self.registry.getOrCreateClass(t))
    elif key == URIRef('http://www.w3.org/2002/07/owl#complementOf'):
      self.kind = 'complement'
      for t in getRdfObjectList(self.g, value):
        self.implements.add(self.registry.getOrCreateClass(t))
    elif key == URIRef('http://www.w3.org/2002/07/owl#complementOf'):
      self.kind = 'complement'
      for t in getRdfObjectList(self.g, value):
        self.implements.add(self.registry.getOrCreateClass(t))
    elif key == 'kind':
      if self.kind == 'unknown':
        self.kind = 'class'
      if value == rdfs_Datatype:
        self.kind = 'datatype'
    elif key == owl_onDatatype:
      self.kind = 'datatype'
      self.implements.add(self.registry.getOrCreateClass(value))
      # TODO: Deal with constraints
  def getAbsNS(self, t='I'):
    if t == 'I' and str(self.uri) in native_types and native_types[str(self.uri)][0]:
      return native_types[str(self.uri)][0]
    s = '\\'.join(split_uri(self.uri, t, not t))
    if t:
      s = '\\' + s
    return s
  def getModifiers(self):
    res = set()
    for t in self.getConstituents({'direct'}):
      if str(t.uri) in native_types and native_types[str(t.uri)][1]:
        res.add((t.getAbsNS('I'), native_types[str(t.uri)][1]))
    return res
  def getName(self):
    return getName(self.uri)
  def getDirPath(self):
    return '/'.join(split_uri(self.uri)[:-1])
  def getAbsPath(self):
    return '/'.join(split_uri(self.uri)) + '.mod'
  def getConstituents(self, flags=set()):
    res = set()
    if   self.kind == 'class':
      res.add(self)
      if str(self.uri) in native_types and native_types[str(self.uri)][2] and 'direct' not in flags:
        res.add(self.registry.getOrCreateClass(URIRef('http://www.w3.org/2001/XMLSchema#string')))
    elif self.kind == 'union' or self.kind == 'datatype':
      for t in self.implements:
        res |= t.getConstituents(flags)
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
  def genTypeConstraint(self, flags):
    parts = set(t.getAbsNS('I') for t in self.getConstituents(flags))
    return parts
  def serialize(self):
    extra_context = set()
    for p in self.property.values():
      extra_context |= p.context
    extra_context = sorted(extra_context)
    if self.kind != 'class':
      return
    nt = native_types.get(str(self.uri)) or [None, None, None]
    if nt[0] and not nt[2]:
      return
    s = '<?php\n\n'
    s += 'declare(strict_types = 1);\n'
    s += 'namespace '+self.getAbsNS(None)+';\n\n'
    if extra_context:
      s += f'const EC{self.i} = {json.dumps(extra_context)};\n\n'
    if self.comment:
      s += '/**\n' + ''.join([f" * {s}\n" for s in wrapper.wrap(text='\n'.join(self.comment))]) + ' */\n'
    if nt[2]:
      s += 'interface D_'
    else:
      s += 'interface I_'
    s += self.getName()+' extends \\auto\\POJO'
    if len(self.implements):
      s += ', '
      s += ', '.join([cls.getAbsNS('I') for cls in self.implements])
    s += ' {\n'
    s += '  const NS = '+json.dumps(sorted(self.context))+';\n'
    s += '  const IRI = '+json.dumps(self.uri)+';\n\n'
    for piri, property in self.property.items():
      pname = getName(piri)
      if property.comment:
        s += '  /**\n' + ''.join([f"   * {s}\n" for s in wrapper.wrap(text='\n'.join(property.comment))]) + '   */\n'
      t  = ''
      tv = ''
      if property.type:
        t  = property.genTypeConstraint({'direct'})
        tv = property.genTypeConstraint({'varadic'})
      st = property.getType()
      pp = json.dumps(property.uri)
      pp += ',' + json.dumps(st.uri if st else None)
      pp += ',['+','.join(f'EC{self.i}[{extra_context.index(s)}]' for s in property.context)+']'
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
      s += 'abstract class A_' + self.getName() + ' implements D_' + self.getName() + ' {\n'
    else:
      s += 'class C_' + self.getName() + ' implements I_' + self.getName() + ' {\n'
    pvs = set()
    for piri, property in self.getAllProperties().items():
      pname = getName(piri)
      cname = property.getName()
      t  = ''
      tv = ''
      if property.type:
        t  = property.genTypeConstraint({'direct'})
        tv = property.genTypeConstraint({'varadic'})
      if property not in pvs:
        pvs.add(property)
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
      m = ''
      if property.type:
        types = sorted([*property.type.getInstTypes()])
        m = json.dumps([*property.type.getModifiers()])
      ser = json.dumps(types)
      if property.isArray():
        s += '\\auto\\array_flatten($value,'+ser+','+m+')'
      else:
        if len(types):
          s += '\\auto\\deser($value,'+ser+','+m+')'
        else:
          s += '$value'
      s += '; }\n'
      if property.isArray():
        s += '  public function add_'+pname+'('+tv+' $value) : void { $this->var_'+cname+' = array_merge($this->var_'+cname+', \\auto\\array_flatten($value,'+ser+','+m+')); }\n'
        s += '  public function del_'+pname+'('+tv+' $value) : void { $this->var_'+cname+' = array_diff ($this->var_'+cname+', \\auto\\array_flatten($value,'+ser+','+m+')); }\n'
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
    Path(self.getDirPath()).mkdir(parents=True, exist_ok=True)
    with open(self.getAbsPath(), 'w') as f:
      print(s, file=f)
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
  def __init__(self, registry, uri):
    Property.i += 1
    self.i = Property.i
    self.context = set()
    self.registry = registry
    self.g = registry.g
    self.classes = set()
    self.uri = uri
    self.uris = {self.uri}
    self.type = None
    self.comment = []
    self.nullable = True
    self.objectproperty = False
    self.datatypeproperty = False
    self.functionalproperty = False
  def getName(self):
    return getName(self.uri)
  def setMeta(self, key, value, alias):
    if key == URIRef('http://www.w3.org/2000/01/rdf-schema#range'):
      self.type = self.registry.getOrCreateClass(value)
    elif key == URIRef('http://www.w3.org/2000/01/rdf-schema#comment'):
      self.comment.append(value)
    elif key == rdf_domain:
      if str(value) == '*':
        for s, p, o in chain(self.g['*'].triples((None, RDF.type, owl_Class)),
                             self.g['*'].triples((None, RDF.type, rdfs_Class))):
          self.setMeta(key, s, alias)
      else:
        cls = self.registry.getOrCreateClass(value)
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
    elif key == dpa_context:
      self.context.add(value)
  def sameAs(self, value):
    if value in self.registry.property:
      prev = self.registry.property[value]
      self.registry.property[self.uri] = prev
      prev.uris.add(self.uri)
    else:
      self.registry.property[value] = self
      self.uri = value
      self.uris.add(value)
  def genTypeConstraint(self, flags=set()):
    if not self.type:
      return None
    parts = self.type.genTypeConstraint(flags)
    if self.nullable:
      parts.add('null')
    res = '|'.join(sorted([*parts]))
    if self.isArray():
      if 'varadic' in flags:
        res += '|array...'
      else:
        res = 'array/*['+res+']*/'
    if res == 'null':
      res = ''
    return res
  def isArray(self):
    return not self.datatypeproperty
  # If it can be only 1 type, return it
  def getType(self):
    if self.type and self.type.kind == 'class':
      return self.type
    return None
Property.i = 1

def fixup(x):
  if not isinstance(x, URIRef):
    return x
  for old, new in ({
    "http://www.w3.org/ns/activitystreams#": "https://www.w3.org/ns/activitystreams#" # They absolutely wrecked it all with this change...
  }).items():
    if x.startswith(old, new):
      x = URIRef(new + x[len(old):])
  return x

def filesof(g, t):
  res = set()
  for f, g in g.items():
    if f != '*' and (t in g):
      res.add(f)
  return res

def generate(files,cfiles):
  ga = Graph()
  g = {}
  for f in files:
    print("parsing file: "+f)
    g2 = Graph()
    g3 = Graph()
    g2.parse(f, format='turtle')
    for s, p, o in g2:
      s=fixup(s)
      p=fixup(p)
      o=fixup(o)
      g3.add((s,p,o))
    g[f] = g3
    ga += g3
  g['*'] = ga
  r = Registry(g)
  for context in cfiles:
    ctx = r.getOrCreateContext(context)
    for v in ctx.fldmap.values():
      if v.startswith('http://') or v.startswith('https://'):
        g['*'].add((URIRef(v), dpa_context, URIRef(ctx.uri)))
    files = set()
    for s, p, o in g['*'].triples((context, URIRef('http://dpa.li/ns/owl/fixes/meta#target-ontology'), None)):
      files |= filesof(g, (o, RDF.type, owl_Ontology))
      if not files:
        print(f"no file found containing ontology <{o}>", file=sys.stderr)
    for s, p, o in g['*'].triples((context, URIRef('http://dpa.li/ns/owl/fixes/meta#target-file'), None)):
      files.add(str(o))
    for f in files:
      for s in g[f].subjects():
        t = (s, dpa_context, context)
        g[f].add(t)
        g['*'].add(t)
  for s, p, o in chain(g['*'].triples((None, RDF.type, owl_Class)),
                       g['*'].triples((None, RDF.type, rdfs_Class)),
                       g['*'].triples((None, RDF.type, rdfs_Datatype))):
    ci = r.getOrCreateClass(s)
    ci.setMeta('kind', o)
    for s, p, o in g['*'].triples((s, None, None)):
      ci.setMeta(p, o)
  for pt in owl_properties:
    for s, p, o in g['*'].triples((None, RDF.type, pt)):
      for s, p, o in chain(g['*'].triples((s, owl_equivalentProperty, None)),
                           g['*'].triples((s, owl_sameAs, None))):
        property = r.getOrCreateProperty(s)
        property.sameAs(o)
      property = r.getOrCreateProperty(s)
      property.setMeta('kind', pt, s)
      for s, p, o in chain(g['*'].triples((s, None, None))):
        property.setMeta(p, o, s)
  r.serialize()
  r.info()

def filterfiles(l):
  return (f for f in l if os.path.isfile(f))

owl = [*filterfiles(
    glob('vocab/**/*.owl', recursive=True)
  + glob('vocab/**/*.ttl', recursive=True)
  + glob('download/vocab/**/*.owl', recursive=True)
  + glob('download/vocab/**/*.ttl', recursive=True)
)]
context = [*filterfiles(glob('download/context/http*:/**/*', recursive=True))]
context = [re.sub('^(https?:/)','\\1/',c[17:]) for c in context]

generate(owl, context)

