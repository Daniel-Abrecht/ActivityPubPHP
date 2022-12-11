
OWLS=$(wildcard vocab/*)

all: auto/.done

auto/.done: $(OWLS)
	rm -rf auto/
	./mkphpclass.py $^

clean:
	rm -rf auto/
