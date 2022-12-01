
OWLS=$(wildcard owl/*.owl)

all: auto/.done

auto/.done: $(OWLS)
	rm -rf auto/
	./mkphpclass.py $^

clean:
	rm -rf auto/
