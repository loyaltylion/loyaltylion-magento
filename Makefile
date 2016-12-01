tar: build/src.tar
	tar build/src.tar ./app
package: 
	mgbuild build-config.php
clean:
	rm build/src.tar
