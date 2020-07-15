# ComHisto - Historique du code INSEE des communes
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
avec un référentiel à jour des communes comme (celui de l'INSEE)[https://www.insee.fr/fr/information/2560452]
ou une base géographique IGN
comme (Admin-Express)[https://geoservices.ign.fr/documentation/diffusion/telechargement-donnees-libres.html#admin-express].
Finalement, ils ne remplissent plus leur fonction de localisant.

Or, sur le fond, le code INSEE d'une commune disparue, par exemple fusionnée,
reste un localisant à condition de disposer du référentiel adhoc.
De plus, il peut être préférable de conserver un code INSEE périmé car en cas de rétablissement il redevient valide
et la conservation du code périmé dans la base évite alors des erreurs de localisation.

L'idée est donc de créer un nouveau référentiel, appelé "Historique du code INSEE des communes" (ComHisto)
contenant tous les codes INSEE des communes ayant existé depuis le 1/1/1943
et associant à chacun des informations versionnées permettant de retrouver l'état de la commune
à une date donnée.  
Ainsi les codes INSEE intégrés un jour dans une base restent valables et peuvent être utilisés par exemple pour géocoder
l'information ou pour la croiser avec un référentiel à jour des communes.
Ce référentiel peut être généré à partir des informations du COG publiées par l'INSEE
et peut être géocodé à partir des informations d'Admin-Express publiées par l'IGN.

## Résultat (provisoire en test)
Le fichier [exhisto.yaml](insee/exhisto.yaml) spécifie le schéma du référentiel ;
le champ $schema définit le schéma JSON des données et le champ contents donne un exemple de contenu.

Le fichier [histo.yaml](insee/histo.yaml) contient le référentiel produit à partir du COG au 1/1/2020.
