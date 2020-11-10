# Génération des tables comhistog3 et elit
# Fichier comhisto/croise2/Makefile - Mis à jour le 9/11/2020
all: verifcheflieu comhistog3
clean:
	rm -f histelitp.yaml *.pser verifcheflieu comhistog3
verifcheflieu: ../elits2/histelitp.yaml verifcheflieu.php histo.inc.php centelits.inc.php
	php verifcheflieu.php ; touch $@
comhistog3: ../elits2/histelitp.yaml fcomhisto.php histo.inc.php centelits.inc.php
	php fcomhisto.php ; touch $@
../elits2/histelitp.yaml:
	cd ../elits2 ; make histelitp.yaml
