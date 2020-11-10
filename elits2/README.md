# Méthode pour comparer les territoires des différentes versions
La méthode pour comparer les territoires associés aux différentes versions de code Insee est fondée
sur la définition d'**éléments administratifs intemporels** (élits)
qui correspondent généralement au territoire associé au code Insee au 1/1/1943 avant les fusions.
Ainsi le territoire de chaque version pourra être défini par un ensemble d'élits
et ces territoires pourront ainsi facilement être comparés entre eux.

On commence par simplifier certaines opérations qui ne correspondent ni à une fusion, ni à une scission
et qui sont détaillées ci-dessous.

Puis, on fait correspondre à chaque version d'entité un ensemble d'élits.

### Simplifications

Les 6 dissolutions simplifiées en fusions sont :
  - dissolution de 08227 (Hocmont) le 2/3/1968,
  - dissolution de 51606 (Verdey) le 12/12/1966,
  - dissolution de 45117 (Creusy) le 1/1/1965,
  - dissolution de 60606 (Sarron) le 9/7/1951,
  - dissolution de 51385 (Moronvilliers) le 17/6/1950,
  - dissolution de 77362 (Pierrelez) le 8/7/1949.

Les 6 créations de commune à partir d'autres communes simplifiées en scissions sont :
  - création de 38567 (Chamrousse) le 15/2/1989,
  - création de 27701 (devenu Val-de-Reuil) le 28/9/1981,
  - création de 91692 (Les Ulis) le 19/2/1977,
  - création de 57766 (Saint-Nicolas-en-Forêt) le 1/1/1958,
  - création de 29302 (devenu Pont-de-Buis-lès-Quimerch) le 27/8/1949,
  - création de 46339 (Saint-Jean-Lagineste) le 17/6/1948.

### Eléments administratifs intemporels (élits)
L'objectif des éléments administratifs intemporels (élits) est de comparer entre eux les territoires
associés aux différentes versions de code Insee.  
Ils correspondent généralement au territoire associé au code Insee au 1/1/1943,
sauf dans le cas où ce territoire a été réduit par scission avant une fusion (comme par exemple 97414) ;
dans ce cas l'élit est le territoire le plus petit après ces scissions.  
De manière générale:

- chaque code Insee correspond à un et un seul élit,
  sauf ceux correspondant à un changement de code ainsi que les 3 communes constituées d'arrondissements municipaux
  qui ne correspondent à aucun élit ;
- chaque élit correspond à un et un seul code Insee par lequel il est identifié ;
- dans les cas complexes, le territoire associé à un élit peut être défini comme l'intersection des territoires des versions
  du code Insee moins l'union des territoires des autres codes Insee ;
- les élits forment une partition du territoire ayant été concerné par le référentiel ;
- tout territoire associé à une version de code Insee peut être défini par un ensemble d'élits.

Plus précisément :

- les élits d'un code correspondant à une association correspond au territoire de la commune
  sans ses entités associées ;
- les élits d'un code correspondant à une commune nouvelle correspond,
  s'il existe une commune déléguée propre, à son territoire,
  sinon au territoire de la commune nouvelle sans ses communes déléguées ;
- la commune nouvelle de Blaignan-Prignac (33055) est une exception à la règle précédente :
  son territoire est composé, d'une part, de celui de sa commune déléguée propre 33055, qui correspond à l'élit 33055,
  et, d'autre part, au territoire correspondant à l'élit 33338 qui n'appartient à aucune de ses communes déléguées,
  ce territoire est celui de l'ancienne commune de Prignac-en-Médoc (33338) qui a fusionné dans la commune nouvelle
  sans que ce territoire soit intégré dans une des communes déléguées.

Attention les élits ne sont pas stables au travers des éditions successives du référentiel.
Cela signifie qu'**ils ne sont intemporels que pour une édition donnée de référentiel**.

Le [fichier GeoJSON des elits est disponible ici](export/elit.7z).
Le [fichier Yaml non géoréférencé des codes Insee avec les élits est disponible ici](elits2/histelit.yaml).

