# Génération des tables comhistog3 et elit
# Fichier comhisto/croise2/Makefile - Mis à jour le 9/11/2020
all: verifcheflieu comhistog3
clean:
	rm -f histelitp.yaml *.pser verifcheflieu comhistog3
verifcheflieu: histelitp.yaml verifcheflieu.php histo.inc.php centelits.inc.php
	php verifcheflieu.php ; touch $@
comhistog3: histelitp.yaml fcomhisto.php histo.inc.php centelits.inc.php
	php fcomhisto.php ; touch $@
histelitp.yaml: corrige.php ../elits2/histelit.yaml
	php corrige.php > $@
../elits2/histelit.yaml:
	cd ../elits2 ; make histelit.yaml
