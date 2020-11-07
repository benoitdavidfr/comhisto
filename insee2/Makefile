# Génération du Rpicom, de Histo et vérification / à leur schéma plus vérification du schéma avec exemples
# Fichier comhisto/insee2/Makefile - Mis à jour le 5/11/2020
#
# les ojectifs sans extension Yaml correspondent à la vérification du fichier Yaml / son schéma
all: histo
clean:
	rm -f histo.yaml rpicom.yaml com20200101.yaml *.pser
# Génération des fichiers html et déplacement dans ../docs/ utilisé par GitHub Pages
specs.html: frpicom.php
	php frpicom.php specs > $@ ; mv $@ ../docs/
mvtserreurs.html: frpicom.php
	php frpicom.php mvtserreurs > $@ ; mv $@ ../docs/
# Vérif. de histo / son schéma
histo: histo.yaml exhisto
	php check.php comhisto/insee2/histo
# Vérif. de exhisto
exhisto: exhisto.yaml
	php check.php comhisto/insee2/exhisto
# génération de histo.yaml
histo.yaml: rpicom.yaml fhisto.php
	php fhisto.php enregistreHisto
# génération de rpicom.yaml
rpicom.yaml: com20200101.yaml frpicom.php
	php frpicom.php enregistreRpicom
# Vérif. de com20200101 / son schéma exfcoms
com20200101: com20200101.yaml exfcoms
	php check.php comhisto/insee2/com20200101
# Vérif. des exemples de exfcoms / au schéma défini dans exfcoms
exfcoms: exfcoms.yaml
	php check.php comhisto/insee2/exfcoms
# génération de com20200101.yaml
com20200101.yaml: ../data/communes2020.csv fetat.php
	php fetat.php ../data/communes2020.csv 2020-01-01 csv > $@
