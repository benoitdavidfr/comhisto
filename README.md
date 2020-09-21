# Référentiel communal historique simplifié (ComHisto)
### Utilisation du code Insee des communes comme référentiel pivot

## Objectif de ce projet
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

La présente proposition consiste donc à créer un nouveau référentiel, appelé "Référentiel communal historique simplifié" (ComHisto),
contenant tous les codes INSEE des communes ayant existé depuis le 1/1/1943
et leur associant les versions successives géoréférencées permettant de retrouver l'état de l'entité à une date donnée.  
Ainsi les codes Insee intégrés un jour dans une base restent valables et peuvent être utilisés, par exemple pour géocoder
l'information ou pour la croiser avec un référentiel à jour des communes,
à *condition cependant de conserver dans la base métier la date de validité du code Insee utilisé*.
Ce référentiel a été généré par croisement des informations du COG publiées par l'Insee
et des informations d'Admin-Express publiées par l'IGN.
Sa date de validité est le 1/1/2020.

Ce référentiel, permettant de géocoder un ancien code Insee, est mis à disposition
sous la forme d'un fichier [au format GeoJSON](https://fr.wikipedia.org/wiki/GeoJSON)
[sur data.gouv ici](https://static.data.gouv.fr/resources/code-officiel-geographique-cog/20200920-175314/comhistog3.geojson)
et [zippé ici (6.0 Mo)](export/comhistog3.7z).
Il est [documenté plus précisément ici](export/README.md).

## Limites du référentiel
**Attention**, les limites suivantes doivent être prises en compte :

- Afin de ne pas complexifier le modèle de données, l'historique des communes a été simplifié dans 12 cas particuliers
  en assimilant les 6 dissolutions de communes à des fusions
  et les 6 créations de commune à partir d'autres communes à des scissions ;
  ces 12 cas particuliers sont listés ci-dessous.
- Afin de réduire la taille du fichier GeoJSON, la géométrie des limites est simplifiée
  en utilisant l'[algorithme de Douglas et Peucker](https://fr.wikipedia.org/wiki/Algorithme_de_Douglas-Peucker)
  avec une résolution de 10**-3 degrés soit environ 100 mètres ;
  cette simplification n'est pas effectuée dans quelques cas où elle génèrerait des erreurs de construction de polygones.
- certaines limites non disponibles dans la version d'Admin-Express du 1/1/2020 sont approximées en utilisant
  une [décomposition de Voronoï](https://fr.wikipedia.org/wiki/Diagramme_de_Vorono%C3%AF) sur les entités valides au 1/1/2020.
- les éventuels transferts de parcelles entre communes ne sont pas pris en compte,
- lorsqu'une commune est absorbée puis rétablie, on fait l'hypothèse que sa géométrie est identique avant l'absorption
  et après le rétablissement.

## Erreurs du référentiel
De plus, **attention**, la production de ce référentiel est en cours et les résultats ne sont disponibles qu'à titre expérimental.

Enfin, il existe des erreurs connues:

- absence des communes de St Barth et de St Martin.
- erreur sur 78613/91613 (Thionville)


# Démarche de construction du référentiel
La suite de ce document détaille la démarche suivie pour définir ce nouveau référentiel.

## 1ère étape - partir des données du COG de l'Insee
La première étape consiste à produire, à partir des données de mouvements et de l'état du COG Insee au 1/1/2020,
l'historique de chaque code Insee sous la forme de versions datées pour chaque code Insee
présentées dans un document Yaml facile à consulter (par un humain et une machine) et à exploiter (par une machine).

Une première version de ce [fichier appelé histov.yaml est disponible ici](insee/histov.yaml).
Sa structuration est spécifiée par un schéma JSON défini dans le champ $schema du fichier [exhisto.yaml](insee/exhisto.yaml) ;
le champ contents donnant des exemples d'enregistrements.

Le fichier Insee des mouvements est complété de certaines données manquantes
(comme par exemple la prise en compte des communes de Mayotte devenu un DOM le 31/3/2011)
et corrigé de quelques erreurs manifestes.

Cette étape est [documentée plus en détail ici](insee/README.md).

## 2ème étape - construire des éléments administratifs intemporels (elits)
On appelle dans la suite *entité* une commune simple, une commune associée, une commune déléguée ou un arrondissement municipal.  
Les 3 derniers types d'entités sont appelés *entités rattachées*.

La seconde étape consiste à :

- appliquer des simplifications assimilant à des fusions les 6 dissolutions suivantes :
    - dissolution de 08227 (Hocmont) le 2/3/1968,
    - dissolution de 51606 (Verdey) le 12/12/1966,
    - dissolution de 45117 (Creusy) le 1/1/1965,
    - dissolution de 60606 (Sarron) le 9/7/1951,
    - dissolution de 51385 (Moronvilliers) le 17/6/1950,
    - dissolution de 77362 (Pierrelez) le 8/7/1949.

- et assimilant à des scissions les 6 créations de commune à partir d'autres communes suivantes :
    - création de 38567 (Chamrousse) le 15/2/1989,
    - création de 27701 (devenu Val-de-Reuil) le 28/9/1981,
    - création de 91692 (Les Ulis) le 19/2/1977,
    - création de 57766 (Saint-Nicolas-en-Forêt) le 1/1/1958,
    - création de 29302 (devenu Pont-de-Buis-lès-Quimerch) le 27/8/1949,
    - création de 46339 (Saint-Jean-Lagineste) le 17/6/1948.

- faire correspondre à chaque version d'entité un ensemble d'**éléments administratifs intemporels** (élits).

Ces élits correspondent généralement au territoire associé au code Insee au 1/1/1943,
sauf dans le cas où ce territoire est réduit pas scission avant une fusion ;
dans ce cas l'élit est le territoire le plus petit après ces scissions.  
De manière générale:
  - il existe un et un seul elit pour chaque *code Insee ne correspondant pas à un changement de code*
    et chaque élit correspond à un et un seul code Insee ;
  - le territoire associé à un élit est l'intersection des territoires des versions de son code Insee
    moins l'union des territoires des autres codes Insee ;
  - le territoire de toute version de code Insee corespond à un ensemble d'élits ;
  - à tout moment les élits forment une partition du territoire ayant été concernés par le référentiel.

Atention cependant les élits ne sont pas stables au travers des versions successives du référentiel.
  
Le [fichier GeoJSON des elits est disponible ici](export/elit.7z).
Le [fichier Yaml non géoréférencé des codes Insee avec les elits est disponible ici](elits/histelit.yaml).



## 3ème étape - géoréférencement des entités valides à partir des données IGN 
Le produit IGN Admin-Express COG version 2020 permet de géoréférencer les zones correspondant à une commune
ou à une entité rattachée valides au 1/1/2020.

On utilise aussi Admin-Express pour renseigner la localisation du chef-lieu associé à chaque commune valide.

## 4ème étape - localisation des chefs-lieux des entités périmées 
On complète les chefs-lieux par ceux des entités périmées par scrapping de Wikipédia et saisie interactive à partir des cartes IGN
et de Wikipédia.

## 5ème étape - croisement des données Insee avec les données IGN
Les entités valides, dont on connait la géométrie, permettent de définir la géométrie des elits correspondants.
Si une entité correspond à un seul élit alors la géométrie de l'élit est celle de l'entité.
Sinon, la géométrie de l'entité est découpée par l'algorithme de Voronoï en elits
en se fondant sur les chefs-lieux asssociés aux élits.
Puis, chaque version étant définie par un ensemsemble d'élits, sa géométrie peut être reconstruite.

Chaque version d'entité est identifiée par son code Insee suffixé par le caractère '@' et la date de création de la version.

Cependant, cela n'est pas suffisant car certains codes Insee correspondent à une date donnée à 2 entités distinctes.
Par exemple, à la suite de la création le 1/1/2016 de la commune nouvelle d'Arboys en Bugey,
le code '01015' correspond en même temps à cette commune nouvelle et à Arbignieu, une de ses communes déléguées.
Ainsi, pour identifier chaque entité versionnée, on préfixe l'id défini ci-dessus par le caractère 's' pour une commune simple
et 'r' pour une entité rattachée.
Ainsi la version de la commune simple d'Arboys en Bugey sera identifiée par `s01015@2016-01-01`
et celle de la commune déléguée d'Arbignieu par `r01015@2016-01-01`.

Ce croisement nécessite plusieurs corrections.
Le fichier Yaml corrigé des codes Insee avec les elits est disponible ici](croise/histelitp.yaml).

## 6ème étape - export du référentiel
Enfin, le référentiel est exporté sous la forme d'[un fichier GeoJSON zippé et mis à disposition](export/comhistog3.7z)
et décrit [ici](export/README.md).
