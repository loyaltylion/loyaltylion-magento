all: package
build/src.tar:
	tar -cvf build/src.tar ./app
package: build/src.tar
	mgbuild build-config.php
clean:
	rm -r build/*
