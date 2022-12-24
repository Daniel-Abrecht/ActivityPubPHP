
OWL += https\://raw.githubusercontent.com/w3c/activitystreams/master/vocabulary/activitystreams2.owl
OWL += https\://raw.githubusercontent.com/w3c/vc-data-integrity/main/vocab/security/vocabulary.ttl
OWL += https\://raw.githubusercontent.com/w3c/vc-data-model/main/vocab/credentials/credentials.ttl

CONTEXT += https\://www.w3.org/ns/activitystreams
CONTEXT += https\://w3id.org/security/v1
CONTEXT += https\://w3id.org/security/v2
CONTEXT += https\://www.w3.org/2018/credentials/v1

DOWNLOAD += $(patsubst %,download/context/%,$(CONTEXT))
DOWNLOAD += $(patsubst %,download/vocab/%,$(OWL))

all: auto/.done

download/.done: $(DOWNLOAD)
	patch download/vocab/https:/raw.githubusercontent.com/w3c/vc-data-integrity/main/vocab/security/vocabulary.ttl <patch/security.ttl.patch
	patch download/vocab/https:/raw.githubusercontent.com/w3c/vc-data-model/main/vocab/credentials/credentials.ttl <patch/credentials.ttl.patch
	touch $@

download/context/%:
	mkdir -p "$(dir $@)"
	curl -fL --silent --max-redirs 5 -H 'Accept: application/ld+json' "$*" -o "$@"

download/vocab/%:
	mkdir -p "$(dir $@)"
	curl -fL --silent --max-redirs 5 "$*" -o "$@"

auto/.done: download/.done
	rm -rf auto/
	mkdir -p auto/
	awk '/\/* *override: *{/{flag=1;print "{";next}/*\//{flag=0}flag' override/*.php | jq -sc add >auto/override_meta.json
	./mkphpclass.py

clean:
	rm -rf auto/
