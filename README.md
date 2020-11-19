# Référentiel communal historique simplifié (ComHisto)
### Utilisation du code Insee des communes comme référentiel pivot
Mise à jour importante le 8/11/2020.

## Objectif du projet
L'objectif de ce projet est d'améliorer l'utilisation comme référentiel pivot du code Insee des communes.

De nombreuses bases de données, appelées par la suite bases métier, par exemple des bases de décisions administratives,
utilisent le code Insee des communes pour géoréférencer leur contenu, c'est à dire, dans l'exemple, chaque décision administrative.

Or, ces codes Insee évoluent, notamment en raison de la volonté de réduire le nombre de communes,
par fusion de communes et par création de communes nouvelles.
Pour en tenir compte, ces codes devraient donc être modifiés dans les bases métier.
Cependant ces modifications ne sont généralement pas effectuées
et en conséquence les codes Insee ainsi contenus dans ces bases perdent leur signification
car ils ne peuvent plus être croisés avec un référentiel à jour des communes,
comme [le code officiel géographique (COG) de l'Insee](https://www.insee.fr/fr/information/2560452),
ou une base géographique IGN,
comme [Admin-Express](https://geoservices.ign.fr/documentation/diffusion/telechargement-donnees-libres.html#admin-express).
Finalement, ils ne remplissent plus leur fonction de géoréférencement.

Or, sur le fond, le code Insee d'une commune abrogée, par exemple fusionnée,
reste un localisant à condition de disposer de l'historique des codes Insee.
De plus, il peut être dans certains cas préférable dans une base de conserver un code Insee périmé
car le géoréférencement peut être plus précis et peut redevenir valide en cas de rétablissement.
La conservation du code périmé dans la base évite ainsi des erreurs ou des approximations de géoréférencement.

Enfin, il n'existe pas de méthode simple et fiable pour actualiser un référentiel comme celui des communes.
Par exemple, si une commune est soumise à une réglementation et qu'elle fusionne avec une autre qui ne l'est pas,
il est impossible de conserver l'information en affectant l'information au niveau des communes. 

La présente proposition consiste donc à créer un nouveau référentiel, appelé "Référentiel communal historique simplifié" (ComHisto),
contenant tous les codes INSEE des communes ayant existé depuis le 1/1/1943
et leur associant les versions successives géoréférencées permettant de retrouver l'état de l'entité à une date donnée.  
Ainsi les codes Insee intégrés dans une base après le 1/1/1943 restent valables et peuvent être utilisés, par exemple pour géocoder
l'information ou pour la croiser avec un référentiel à jour des communes,
à *condition cependant de conserver dans la base métier la date de validité du code Insee utilisé*.
Ce référentiel a été généré par croisement des informations du COG publiées par l'Insee
et des informations d'Admin-Express publiées par l'IGN.
Sa date de validité est le 1/1/2020.

Ce référentiel, permettant de géocoder un ancien code Insee, est mis à disposition
sous la forme d'un fichier [au format GeoJSON](https://fr.wikipedia.org/wiki/GeoJSON)
[publié sur data.gouv ici](https://static.data.gouv.fr/resources/code-officiel-geographique-cog/20200920-175314/comhistog3.geojson)
et [zippé ici (6.0 Mo)](export/comhistog3.7z).
Il est [documenté plus précisément ici](export/README.md).

## Mise à disposition du référentiel
Outre sa mise à disposition au format GeoJSON, le référentiel est aussi proposé:

- sous la forme de cartes représentant les différentes versions associées aux codes Insee,
- sous la forme d'une API.

Ces 2 types d'accès sont [décrits ici](api/README.md).

## Limites du référentiel
**Attention**, aux limites suivantes :

- afin de ne pas complexifier le modèle de données, l'historique des communes a été simplifié dans 12 cas particuliers
  en assimilant les 6 dissolutions de communes à des fusions
  et les 6 créations de commune à partir d'autres communes à des scissions ;
  ces 12 cas particuliers sont listés plus loin.
- afin de réduire la taille du fichier GeoJSON, la géométrie des limites est simplifiée
  en utilisant l'[algorithme de Douglas et Peucker](https://fr.wikipedia.org/wiki/Algorithme_de_Douglas-Peucker)
  avec une résolution de 10**-3 degrés, soit environ 100 mètres ;
  cette simplification n'est pas effectuée dans quelques cas où elle génèrerait des erreurs de construction de polygones.
- les limites non disponibles dans la version d'Admin-Express du 1/1/2020 sont approximées en utilisant
  une [décomposition de Voronoï](https://fr.wikipedia.org/wiki/Diagramme_de_Vorono%C3%AF) sur les entités valides au 1/1/2020.
- les éventuels transferts de parcelles entre communes ne sont pas pris en compte,
- lorsqu'une commune est absorbée puis rétablie, on fait l'hypothèse que sa géométrie est identique avant l'absorption
  et après le rétablissement.

De plus, **attention**, les résultats ne sont disponibles qu'à titre expérimental.


# Démarche de construction du référentiel
La suite de ce document détaille la démarche suivie pour construire ce nouveau référentiel.

## 1ère étape - restructurer les données du COG de l'Insee
La première étape consiste à produire, à partir des données de mouvements et de l'état du COG Insee au 1/1/2020,
l'historique de chaque code Insee sous la forme de versions datées pour chaque code Insee
présentées dans un document Yaml facile à consulter (par un humain et une machine) et à exploiter (par une machine).

Une première version de ce [fichier appelé histo.yaml est disponible ici](insee2/histo.yaml).
Sa structuration est spécifiée par un schéma JSON défini dans le champ $schema du fichier [exhisto.yaml](insee2/exhisto.yaml) ;
le champ contents donnant des exemples d'enregistrements.

Le fichier Insee des mouvements est complété de certaines données manquantes
(comme par exemple la prise en compte des communes de Mayotte devenu un DOM le 31/3/2011)
et corrigé de quelques anomalies.

Cette étape est [documentée plus en détail ici](insee2/README.md).

## 2ème étape - définir une méthode pour comparer les différentes versions
Dans la seconde étape, une méthode est définie pour comparer les territoires associés aux différentes versions de code Insee.
Pour cela, on construit les **éléments administratifs intemporels** (élits)
qui correspondent généralement au territoire associé au code Insee au 1/1/1943 avant les fusions.
Ainsi le territoire de chaque version pourra être défini par un ensemble d'élits
et ces territoires pourront facilement être comparés entre eux.

On commence par simplifier certaines opérations qui ne correspondent ni à une fusion, ni à une scission
puis on fait correspondre à chaque version d'entité un ensemble d'élits.
Enfin on effectue quelques corrections nécessaires au croisement effectué à l'étape 6.
Le fichier Yaml corrigé des codes Insee avec les elits [est disponible ici](elits2/histelitp.yaml).

Cette étape est [documentée plus en détail ici](elits2/README.md).

## 3ème étape - géoréférencer les entités valides à partir des données IGN 
On appelle dans la suite *entité* une commune simple, une commune associée, une commune déléguée ou un arrondissement municipal. 
Les 3 derniers types d'entités sont appelés *entités rattachées*.

Le produit IGN Admin-Express COG version 2020 permet de géoréférencer les zones correspondant à une commune
ou une entité rattachée valide au 1/1/2020.

On utilise aussi Admin-Express pour renseigner la localisation du chef-lieu associé à chaque commune valide.

## 4ème étape - ajouter St Barth et St Martin
Les communes de St Barth et St Martin sont sorties du référentiel et leur géométrie n'est donc pas disponible
dans ADMIN-EXPRESS COG 2020.
Ces géométries sont reconstituées à partir de
[la couche admin-0-countries de Natural Earth 1/10M](https://www.naturalearthdata.com/downloads/10m-cultural-vectors/10m-admin-0-countries/).
 
## 5ème étape - localiser les chefs-lieux des entités périmées 
On complète les chefs-lieux des communes valides par ceux des entités rattachées et périmées par scrapping de Wikipédia
et saisie interactive à partir des cartes IGN et de Wikipédia.
Ces [chef-lieux sont disponibles ici comme fichier GeoJSON](cheflieu/cheflieu.geojson).

## 6ème étape - croiser les données Insee avec celles de l'IGN
Les entités valides, dont on connait la géométrie dans ADMIN-EXPRESS, permettent de définir la géométrie des élits correspondants.
Si une entité correspond à un seul élit alors la géométrie de l'élit est celle de l'entité.
Sinon, la géométrie de l'entité est découpée en élits par
l'[algorithme de **Voronoï**](https://fr.wikipedia.org/wiki/Diagramme_de_Vorono%C3%AF)
en s'appuyant sur la localisation ponctuelle des chefs-lieux asssociés aux élits receuillie à l'étape précédente.
Puis, chaque version étant définie par un ensemble d'élits, sa géométrie est reconstruite par l'union de ces élits.

Chaque version d'entité est identifiée par son code Insee suffixé par le caractère '@' et la date de création de la version.
Cependant, cela n'est pas suffisant car certains codes Insee correspondent à une date donnée à 2 entités distinctes.
Par exemple, à la suite de la création le 1/1/2016 de la commune nouvelle d'Arboys en Bugey,
le code '01015' correspond en même temps à cette commune nouvelle et à Arbignieu, une de ses communes déléguées.
Ainsi, pour identifier chaque entité versionnée, on préfixe l'id défini ci-dessus par le caractère 's' pour une commune simple
et 'r' pour une entité rattachée.
Ainsi la version de la commune simple d'Arboys en Bugey sera identifiée par `s01015@2016-01-01`
et celle de la commune déléguée d'Arbignieu par `r01015@2016-01-01`.

## 7ème étape - exporter le référentiel
Enfin, le référentiel est exporté sous la forme d'[un fichier GeoJSON zippé et mis à disposition](export/comhistog3.7z)
et décrit [ici](export/README.md).
