# Génération du Rpicom et de certains états avec vérification / à leur schéma plus vérification du schéma evec exemples
# Mis à jour le 10/7/2020
#
# les ojectifs sans extension Yaml correspondent à la vérification du fichier Yaml / son schéma
all: rpicom fcoms
clean:
	rm -f rpicom.yaml com20200101.yaml *.pser
fcoms: com20200101
# Vérif. de rpicom / son schéma
rpicom: rpicom.yaml exrpicom
	php insee.php check rpicom.yaml
# Vérif. de exrpicom
exrpicom: exrpicom.yaml
	php insee.php check exrpicom.yaml
# génération de rpicom.yaml
rpicom.yaml: com20200101.yaml insee.php grpmvts.inc.php base.inc.php
	php insee.php brpicom
	php insee.php détailleEvt
# Vérif. de com20200101 / son schéma exfcoms
com20200101: com20200101.yaml exfcoms
	php insee.php check com20200101.yaml
# génération de com20200101.yaml
com20200101.yaml: ../data/communes2020.csv insee.php
	php insee.php buildState 2020-01-01 ../data/communes2020.csv csv > $@
