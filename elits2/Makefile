# Génération d'histelitp.yaml
# Fichier comhisto/elits2/Makefile - Mis à jour le 9/11/2020
all: histelitp
clean:
	rm -f histelitp.yaml histelit.yaml histelit0.yaml *.pser
# Vérification d'histelitp.yaml
histelitp: histelitp.yaml
	php ../insee2/check.php comhisto/elits2/histelitp
# génération de histelitp.yaml
histelitp.yaml: histelit.yaml verifelits.php
	php verifelits.php > $@
# génération de histelit.yaml
histelit.yaml: histelit0.yaml defelit.php
	php defelit.php > $@
# génération de histelit0.yaml
histelit0.yaml: ../insee2/histo.yaml defelit0.php simplif.inc.php
	php defelit0.php > $@
../insee2/histo.yaml:
	cd ../insee2 ; make histo.yaml
