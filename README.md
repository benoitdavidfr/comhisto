# Historique du code INSEE des communes (ComHisto)
### Utilisation du code INSEE des communes comme référentiel pivot

## Objectif de ce projet
L'objectif de ce projet est d'améliorer l'utilisation comme référentiel pivot du code INSEE des communes.

De nombreuses bases de données, par exemple des bases de décisions administratives, utilisent le code INSEE des communes
pour localiser leur contenu, c'est à dire dans l'exemple chaque décision administrative.

Or, ces codes INSEE évoluent, notamment en raison de la volonté de réduire le nombre de communes
par fusion et par création de communes nouvelles.
Ces codes devraient donc être modifiés dans la base pour en tenir compte.
Cependant ces modifications ne sont généralement pas effectuées
et en conséquence les codes INSEE ainsi contenus dans les bases perdent leur signification
car ils ne peuvent plus être croisés
avec un référentiel à jour des communes, comme [celui de l'INSEE](https://www.insee.fr/fr/information/2560452),
ou une base géographique IGN,
comme [Admin-Express](https://geoservices.ign.fr/documentation/diffusion/telechargement-donnees-libres.html#admin-express).
Finalement, ils ne remplissent plus leur fonction de localisant.

Or, sur le fond, le code INSEE d'une commune périmée, par exemple fusionnée,
reste un localisant à condition de disposer du référentiel adhoc.
De plus, il peut être préférable dans une base de conserver un code INSEE périmé car en cas de rétablissement il redevient valide
et la conservation du code périmé dans la base évite alors des erreurs de localisation.

La proposition est donc de créer un nouveau référentiel, appelé "Historique du code INSEE des communes" (ComHisto),
contenant tous les codes INSEE des communes ayant existé depuis le 1/1/1943
et associant à chacun des informations versionnées permettant de retrouver l'état de la commune à une date donnée.  
Ainsi les codes INSEE intégrés un jour dans une base restent valables et peuvent être utilisés par exemple pour géocoder
l'information ou pour la croiser avec un référentiel à jour des communes.
Ce référentiel peut être généré à partir des informations du COG publiées par l'INSEE
et peut être géocodé à partir des informations d'Admin-Express publiées par l'IGN.

## 1ère étape - partir des données du COG de l'INSEE
La première étape consiste à produire, à partir des données de mouvements et de l'état du COG INSEE au 1/1/2020, un document Yaml
facile à consulter (par un humain et une machine) et à exploiter (par une machine) de l'historique de chaque code Insee.
Le fichier [exhisto.yaml](insee/exhisto.yaml) spécifie le schéma de ce fichier ;
le champ $schema définit le schéma JSON des données et le champ contents donne un exemple de contenu.

Le fichier [histo.yaml](insee/histo.yaml) contient l'historique de chaque code Insee produit à partir du COG INSEE au 1/1/2020.

## 2ème étape - structuration des versions en zones
En utilisant la topologie définie par l'Insee entre versions, produire à partir de histo.yaml un document Yaml des zones.
Une zone correspond aux différentes versions ayant même extension géographique ;
la définition des zones inclus la relation d'inclusion entre elles.

## 3ème étape - géoréférencement des entités valides en utilisant des données IGN 
Le produit IGN Admin-Express COG version 2020 permet de géoréférencer les zones correspondant à une commune
ou à une entité rattachée (commune associée, commune déléguée ou arrondissement municipal) valide au 1/1/2020.

De même, les versions précédentes d'Admin-Express ou de GéoFLA permettent de géoréférencer des zones correspondant à une commune périmée,
par exemple fusionnée dans une autre.

On utilise aussi Admin-Express pour renseigner la localisation du chef-lieu associé à chaqeu commune valide.

## 4ème étape - construction d'un géoréférencement approché des entités périmées
Il existe un certain nombre d'entités périmées pour lesquelles il est difficile de définir un géoréférencement.
L'idée est dans ce cas de définir un géoréférencement approché en partant de la localisation ponctuelle des chefs-lieux
et en construisant des polygones par l'(algorithme de Voronoï)[https://fr.wikipedia.org/wiki/Diagramme_de_Vorono%C3%AF].

## 5ème étape - publication du référentiel

