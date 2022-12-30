
OWL += https\://raw.githubusercontent.com/w3c/activitystreams/master/vocabulary/activitystreams2.owl
OWL += https\://raw.githubusercontent.com/w3c/vc-data-integrity/main/vocab/security/vocabulary.ttl
OWL += https\://raw.githubusercontent.com/w3c/vc-data-model/main/vocab/credentials/credentials.ttl
OWL += https\://www.w3.org/ns/ldp.ttl

CONTEXT += https\://www.w3.org/ns/activitystreams
CONTEXT += https\://www.w3.org/ns/did/v1
CONTEXT += https\://w3id.org/security/v1
CONTEXT += https\://w3id.org/security/v2
CONTEXT += https\://www.w3.org/2018/credentials/v1
CONTEXT += https\://www.w3.org/ns/ldp

DOWNLOAD += $(patsubst %,download/context/%,$(CONTEXT))
DOWNLOAD += $(patsubst %,download/vocab/%,$(OWL))

export BUILDENV=1
.PRECIOUS:

all: dist/ActivityPub.tar

dist/ActivityPub.tar: pojo/.done $(shell find ./lib/ -type f)
	mkdir -p $(dir $@)
	tar --transform 's|^|/usr/local/share/php/dpa/|' -cf "$@" --exclude=".done" "pojo/" "lib/"
	tar -tf "$@"

download/.done: $(DOWNLOAD)
	-patch download/vocab/https:/raw.githubusercontent.com/w3c/vc-data-integrity/main/vocab/security/vocabulary.ttl <patch/security.ttl.patch
	-patch download/vocab/https:/raw.githubusercontent.com/w3c/vc-data-model/main/vocab/credentials/credentials.ttl <patch/credentials.ttl.patch
	touch $@

download/context/%:
	mkdir -p "$(dir $@)"
	curl -fL --silent --max-redirs 5 -H 'Accept: application/ld+json' "$*" -o "$@"

download/vocab/%:
	mkdir -p "$(dir $@)"
	curl -fL --silent --max-redirs 5 "$*" -o "$@"

build/override_meta.json: $(wildcard lib/override/*.php)
	mkdir -p $(dir $@)
	awk '/\/* *override: *{/{flag=1;print "{";next}/*\//{flag=0}flag' $^ | jq -sc add >"$@"

pojo/.done: download/.done build/override_meta.json script/mkphpclass.py
	rm -rf pojo/
	./script/mkphpclass.py
	touch $@

clean:
	rm -rf dist/ build/ pojo/ download/
