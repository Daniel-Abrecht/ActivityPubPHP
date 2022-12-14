
OWLS=$(wildcard vocab/*)

all: auto/.done

auto/.done: $(OWLS)
	rm -rf auto/
	mkdir -p auto/
	awk '/\/* *override: *{/{flag=1;print "{";next}/*\//{flag=0}flag' override/*.php | jq -sc add >auto/override_meta.json
	./mkphpclass.py $^

clean:
	rm -rf auto/
