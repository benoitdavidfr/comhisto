# Génération d'histelit.yaml
# Fichier comhisto/elits2/Makefile - Mis à jour le 9/11/2020
all: histelit
clean:
	rm -f histelit.yaml histelit0.yaml *.pser
# Vérification d'histelit.yaml
histelit: histelit.yaml
	php ../insee2/check.php comhisto/elits2/histelit
# génération de histelit.yaml
histelit.yaml: histelit0.yaml defelit.php
	php defelit.php > $@
# génération de histelit0.yaml
histelit0.yaml: ../insee2/histo.yaml defelit0.php simplif.inc.php
	php defelit0.php > $@
