# Génération des tables comhistog3 et elit
# Fichier comhisto/croise2/Makefile - Mis à jour le 9/11/2020
all: comhistog3
comhistog3: histelitp.yaml fcomhisto.php
	php fcomhisto.php ; touch $@
histelitp.yaml: corrige.php ../elits2/histelit.yaml
	php corrige.php > $@
../elits2/histelit.yaml:
	cd ../elits2 ; make histelit.yaml
