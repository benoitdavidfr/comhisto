# Construction des chefs-lieux
L'idée est de construire d'une collection des chefs-lieux pour chaque entité, chaque chef-lieu étant localisé par un point.  
En pratique, on peut normalemnt associer à chaque code Insee ayant existé un chef-lieu associé à un point.
Il n'est pas utile de versionner cette information car ces chefs-lieux ne changent pas dans le temps.

Le [fichier GéoJSON zippé](../export/cheflieu.7z) contient ces chefs-lieux avec les caractéristiques suivantes :

- il manque 111 codes Insee qui correspondent :
  - aux COMD/COMA non découpées par Voronoi
  - aux 6 communes n'ayant pas de chef-lieu dans Admin-Express
    - "id","nom_com"
    - "55189","Fleury-devant-Douaumont"
    - "55307","Louvemont-Côte-du-Poivre"
    - "55050","Bezonvaux"
    - "55139","Cumières-le-Mort-Homme"
    - "55039","Beaumont-en-Verdunois"
    - "55239","Haumont-près-Samogneux"
  - aux 2 communes de StBarth et StMartin
- définition des champs
  - `cinsee0` est le code Insee initial
  - `cinseea` est le code Insee actuel c'est dire après changement de code s'il y en a eu un, sinon il est identique à `cinsee`
  - `dlnom` est le dernier nom local, c'est à dire en priviligiant le nom de communé déléguée à celui de la commune nouvelle,
  - `source` définit la source de la géométrie, elle peut prendre les valeurs suivantes :
    - `absence` pour absence de géométrie dans les cas indiqués ci-dessus,
    - `chef_lieu_carto` quand la géométrie provient de la table `chef_lieu_carto` d'Admin-Express
    - `wpgp` quand le point a été saisi à partir de Wikipédia ou du Géoportail.
