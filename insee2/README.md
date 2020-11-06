# Référentiel historique des codes Insee

L'Insee publie la situation des communes au 1er janvier ainsi que les évolutions depuis le 1/1/1943.  
Ce sous-projet consiste à restructurer ces informations afin d'en faciliter la réutilisation
sous la forme d'un document structuré dans le [format Yaml](https://fr.wikipedia.org/wiki/YAML) facile à consulter
(par un humain et une machine) et à exploiter (par une machine). 
Ce document est [disponible ici](histo.yaml) ;
sa structure est formellement définie par un [schéma JSON](https://json-schema.org/) [disponible ici](exhisto.yaml).

Après avoir défini, dans une première partie, la notion d'évènement,
cette page présente, dans une seconde, la structuration du référentiel.
Puis quelques cas particuliers sont listés ainsi que des problèmes restants connus.
Enfin, la liste des modifications apportées aux données Insee est listée et un extrait illustre la structuration du référentiel.

## Définition des évènements sur les codes Insee

### Liens entre codes Insee et entités administratives

Un code Insee à une date donnée peut correspondre:

- à une commune simple (COM),
- à une entité rattachée (ER) à une commune simple qui peut être:
  - une commune associée (COMA),
  - une commune déléguée (COMD),
  - un arrondissement municipal (ARM),
- simultanément à une commune simple et à une commune déléguée.

Dans la suite le terme *entité* désignera une commune simple ou une entité rattachée.

### Définition des opérations sur les entités et des évènements sur les codes Insee

Les opérations sont exprimées sous la forme d'évènements:

- s'appliquant à un code Insee,
- ayant généralement en paramètres un code Insee ou une liste de codes Insee.

Par exemple, l'opération de fusion de la commune d'Amareins (01003)
dans la commune de Amareins-Francheleins-Cesseins (01165) s'exprime en Yaml par:

    {'01003': {évts: {fusionneDans: '01165'}}}

Pour cet évènement de fusion, un évènement `absorbe`, appelé *mirroir*, est défini sur l'objet de l'évènement de fusion
et s'exprime par:

    {'01165': {évts: {absorbe: ['01003']}}}

3 catégories d'opérations sur entités sont définies:

- la première correspond aux opérations *topologiques*, pour lesquelles chaque entité est vue comme une zone géométrique,  
  par exemple l'opération de fusion d'une entité A dans une autre B ;
- la seconde correspond aux opérations *ensemblistes* entre entités rattachées et communes simples,
  chaque commune simple étant associée à un ensemble d'entités rattachées,
  par ex. l'opération par laquelle une entité A devient commune déléguée d'une entité B ;
- enfin, la troisième catégorie d'opérations correspondent à une entrée ou une sortie du référentiel
  ou à un changement de code ou de nom d'une entité.
  

Les types d'opérations, et les types d'évènements correspondants, sont les suivants:

#### Les opérations topologiques

- dissolution d'une entité A par répartition de son territoire entre plusieurs autres entités préexistantes Bi
  - évènements: `A.seDissoutDans.(Bi) /  B.reçoitUnePartieDe.A`
  - exemple: `{45117: {évts: {seDissoutDans: [45093,45313]}, 45093: {évts: {reçoitUnePartieDe: 45117}}, 45313: {évts: {reçoitUnePartieDe: 45117}}}`
- création d'une entité A par agrégation de morceaux de territoire pris à plusieurs autres entités Bi
  - évènements: `A.crééeAPartirDe.(Bi) /  B.contribueA.A`
  - exemple: `{38567: {évts: {crééeAPartirDe: [38422,38478,38529] }}, 38422: {évts: {contribueA: 38567 }}}`
- suppression d'une entité A par fusion de son territoire dans celui d'une autre B
  - évènements: `A.fusionneDans.B / B.absorbe.(A)`
  - exemple: `{'01003': {évts: {fusionneDans: '01165'}}, '01165': {évts: {absorbe: ['01003']}}}`
  - cas particulier:
    le cas de 2 entités qui fusionnent pour en créer une nouvelle avec un nouveau code Insee est traité par une fusion suivie
    d'un changement de code
- une entité A se scinde en 2 pour en créer une nouvelle B, les évènements définissent le type de l'entités créée
  - évènements: `A.seScindePourCréer.(B) / B.crééeCOMParScissionDe.A, B.crééeCOMAParScissionDe.A, B.crééARMParScissionDe.A`
  - exemple: `{97414: {évts: {seScindePourCréer: [97424]}}, 97424: {évts: {crééeCommeSimpleParScissionDe: 97414}}}`

#### Les opérations ensemblistes

- rattachement (par association ou délégation) d'une entité A à une COM B
  - *évènements*: `A.sAssocieA.B / B.prendPourAssociées.(A), A.devientDéléguéeDe.B / B.prendPourDéléguées.(A)`
  - exemple: `{ '02166': {évts: {sAssocieA: '02524'}}, '02524': {évts: {associe: ['02166']}}}`
- détachement d'une entité A d'une COM B
  - évènements: `A.seDétacheDe.B / B.détacheCommeSimples.(A)`
  - exemple: `{'02166': {évts: {seDétacheDe: '02524'}}, '02524': {évts: {détacheCommeSimples: ['02166']}}}`
- attachement conservée d'une entité A à une COM B
  - évènements: `A.resteRattachéeA.B / B.gardeCommeRattachées.(A)`
  - exemple: `{'01070': {évts: {resteRattachéeA: '01165'}}, '01165': {évts: {gardeCommeRattachées: ['01070']}}}`

#### Les autres opérations

- l'entrée d'une entité dans le référentiel est exprimée par une absence d'évènement ;
  l'entrée est généralement effectuée au 1/1/1943 sauf pour les communes de Mayotte, dont l'entrée est datée du 31/3/2011,
  date à laquelle Mayotte est devenu un département francais,
- la sortie du référentiel est exceptionnelle, il s'agit de Saint-Martin et de Saint-Barthélémy 
  - évènement: `A.sortDuPérimètreDuRéférentiel.null`
  - exemple: `{97123: {évts: {sortDuPérimètreDuRéférentiel: null}}}`
- changement de code d'une entité, dans certains cas, notamment quand elle change de département
  - évènements: A.changeDeCodePour.B / B.avaitPourCode.A
  - exemple: `{20004: {évts: {changeDeCodePour: 2A004}}, 2A004: {évts: {avaitPourCode: 20004}}}`
- changement de nom d'une entité pour un autre nom
  - évènement: `A.changeDeNomPour.NouveauNom`
  - exemple: `{'01053': {évts: {changeDeNomPour: Bourg-en-Bresse}}}`
- une commune simple peut être modifiée par la modification d'une de ses entités rattachées
  - évènement: `estModifiéeIndirectementPar`

## Structuration de l'historique des codes Insee

Le référentiel historique des codes INSEE des communes est principalement constitué d'un dictionnaire des codes INSEE des communes
simples et des entités rattachées (communes associées, communes déléguées et arrondissements municipaux) ayant existé depuis le
1/1/1943 associant à chaque code des infos versionnées indexées par la date de la version.  
Outre cette date, chaque version correspond:

- dans un champ `évts` à un ou des évènement(s) de création/modification/suppression de la version, 
  sauf pour la version initiale datée du 1/1/1943, sauf pour les communes de Mayotte,
  dont l'état initial est daté du 31/3/2011, date à laquelle Mayotte est devenu un département francais,
- dans un champ `état` à l'état résultant du/des évènement(s) de l'entité associée au code, valide à partir de la date de la version
  jusqu'à la date de la version suivante s'il y en a une, sinon valide à la date de validité du référentiel ;
  cet état est absent ssi le(s) évènement(s) conduisent à une suppression de l'entité (ex. fusion),
- dans un champ `erat`, la liste des entités rattachées, s'il y en a, déduites de l'état de ces entités rattachées.

Certaines informations peuvent être déduites des informations primaires ; cela est alors signalé dans les commentaires du schéma.  
Outre ce dictionnaire défini dans le champs contents, le document contient différentes champs,
notamment des propriétés Dublin Core (`title`, `description`, `created`, `modified`, `valid`)
ainsi qu'un champ `$schema` contenant une référence vers son schema JSON
et un champ `ydADscrBhv` peut être utilisé pour afficher le fichier.

### Les évènements (champ évts)

Les opérations sur les entités sont décrites par des évènements de création/modification/suppression s'appliquant à un code Insee ;
la plupart prennent en objet un code INSEE ou une liste et, dans ce cas, les codes INSEE objets portent à la même un date un évènement
appelé mirroir.
Certains évènements, comme mentionnés dans les commentaires, peuvent être déduits de leurs évènements mirroirs. Lorsque
l'information déduite est absente alors l'objet de l'évènement est une liste vide.

### L'état (champ état)

Etat résultant des évènements et valide à partir de la date de la version et soit, s'il y a une version suivante, jusqu'à sa
date, soit, sinon, valide à la date de validité du référentiel.
Dans le premier cas on dit que la version est périmée, dans le second qu'elle est valide.

### Liste des entités rattachées (champ erat)

La liste des entités rattachées (communes associées ou déléguées, ou arrondissements municipaux)
est définie pour les communes simples en ayant. Ces infos sont déduites du statut et crat des entités rattachées.
Cette propriété est absente si ces infos ne sont pas déduites.


## Liste des modifications apportées aux données Insee

### Lignes du fichier des mouvements non interprétés car non comprises
Ces lignes sont [listées ici](mvtserreurs.html).

### Correction des mouvements sur Ronchères (89325) et Septfonds (89389)
Sur Ronchères (89325) et Septfonds (89389) les mouvements définis par l'Insee d'association le 1972-12-01
et de rétablissement le 1977-01-01 sont incompatibles.  
Les mouvements du 1/1/1977 sur Ronchères (89325) et Septfonds (89389) sont transformés en resteRattachéeA.  
Ma compréhension est qu'il manque dans le fichier des mouvements les 2 lignes suivantes:

| mod |  date_eff  | typecom_av | com_av | libelle_av | typecom_ap | com_ap | libelle_ap |
| --- | ---------- | ---------- | ------ | ---------- | ---------- | ------ | ---------- |
| 21  | 1977-01-01 |   COMA     | 89325  | Ronchères  |    COMA    | 89325  | Ronchères  |
| 21  | 1977-01-01 |   COMA     | 89389  | Septfonds  |    COMA    | 89389  | Septfonds  |

### Correction d'un mouvement sur Gonaincourt (52224)
Gonaincourt (52224) au 1/6/2016 n'est pas fusionnée mais devient déléguée de 52064.    
Ma compréhension est qu'il manque dans le fichier des mouvements la ligne suivante:

| mod |  date_eff  | typecom_av | com_av | libelle_av | typecom_ap | com_ap | libelle_ap |
| --- | ---------- | ---------- | ------ | ---------- | ---------- | ------ | ---------- |
| 32  | 2016-01-01 |   COMA     | 52224  | Gonaincourt|    COMD    | 52224  | Gonaincourt|

### Correction d'un mouvement sur Bois-Guillaume-Bihorel (76108)
Avant son rétablissement du 1/1/2014, Bois-Guillaume-Bihorel (76108) a une commune déléguée ayant le même code.    
Ma compréhension est qu'il manque dans le fichier des mouvements la ligne suivante:

| mod |  date_eff  | typecom_av | com_av | libelle_av    | typecom_ap | com_ap | libelle_ap    |
| --- | ---------- | ---------- | ------ | ------------- | ---------- | ------ | ------------- |
| 21  | 2014-01-01 |   COMD     | 76108  | Bois-Guillaume|    COM     | 76108  | Bois-Guillaume|


### Redéfinition des évènements sur les arrondissements de Lyon
Pour intégrer:

- la scission en 2 du 7ème arrdt le 8/2/1959 pour créer le 8ème arrdt,
- l'absorbtion de Saint-Rambert-l'Île-Barbe (69232) le 7/8/1963 dans dans le 5ème arrdt,
- la scission en 2 du 5ème arrdt le 12/8/1964 pour créer le 9ème arrdt.

### Saint-Martin et Saint-Barthélemy
Saint-Martin et Saint-Barthélemy sortent du référentiel le 15 juillet 2007 car ils n'appartiennent plus à un DOM.

### Mayotte
Les communes de Mayotte entrent dans le référentiel le 31 mars 2011, date à laquelle Mayotte est devenu un département francais.


## Extrait
L'extrait ci-dessous illustre le contenu du référentiel.


    title: 'Référentiel historique des communes'
    description: 'Voir la documentation sur https://github.com/benoitdavidfr/comhisto'
    created: '2020-11-05T19:19:48+00:00'
    valid: '2020-01-01'
    $schema: 'http://id.georef.eu/comhisto/insee2/exhisto/$schema'
    ydADscrBhv:
      jsonLdContext: 'http://schema.org'
      firstLevelType: AdministrativeArea
      buildName:
        AdministrativeArea: "if (isset($item['now']['état']['name']))\n  return $item['now']['état']['name'].\" ($skey)\";\nelse\n  return '<s>'.array_values($item)[0]['état']['name'].\" ($skey)</s>\";"
      writePserReally: true
    contents:
      '01001':
        '1943-01-01':
          état: { statut: COM, name: 'L''Abergement-Clémenciat' }
      '01002':
        '1943-01-01':
          état: { statut: COM, name: 'L''Abergement-de-Varey' }
      '01003':
        '1943-01-01':
          état: { statut: COM, name: Amareins }
        '1974-01-01':
          évts: { sAssocieA: '01165' }
          état: { statut: COMA, name: Amareins, crat: '01165' }
        '1983-01-01':
          évts: { fusionneDans: '01165' }
      '01004':
        '1943-01-01':
          état: { statut: COM, name: Ambérieu }
        '1955-03-31':
          évts: { changeDeNomPour: Ambérieu-en-Bugey }
          état: { statut: COM, name: Ambérieu-en-Bugey }
      '01005':
        '1943-01-01':
          état: { statut: COM, name: Ambérieux-en-Dombes }
      '01006':
        '1943-01-01':
          état: { statut: COM, name: Ambléon }
      '01007':
        '1943-01-01':
          état: { statut: COM, name: Ambronay }
      '01008':
        '1943-01-01':
          état: { statut: COM, name: Ambutrix }
      '01009':
        '1943-01-01':
          état: { statut: COM, name: Andert-et-Condon }
      '01010':
        '1943-01-01':
          état: { statut: COM, name: Anglefort }
      '01011':
        '1943-01-01':
          état: { statut: COM, name: Apremont }
      '01012':
        '1943-01-01':
          état: { statut: COM, name: Aranc }
      '01013':
        '1943-01-01':
          état: { statut: COM, name: Arandas }
      '01014':
        '1943-01-01':
          état: { statut: COM, name: Arbent }
      '01015':
        '1943-01-01':
          état: { statut: COM, name: Arbignieu }
        '2016-01-01':
          évts: { prendPourDéléguées: ['01015', '01340'] }
          état: { statut: COM, name: 'Arboys en Bugey', nomCommeDéléguée: Arbignieu }
          erat: ['01340']
      '01016':
        '1943-01-01':
          état: { statut: COM, name: Arbigny }
      '01017':
        '1943-01-01':
          état: { statut: COM, name: Argis }
      '01018':
        '1943-01-01':
          état: { statut: COM, name: Arlod }
        '1971-01-01':
          évts: { fusionneDans: '01033' }
      '01019':
        '1943-01-01':
          état: { statut: COM, name: Armix }
      '01020':
        '1943-01-01':
          état: { statut: COM, name: Arnans }
        '1964-06-04':
          évts: { fusionneDans: '01125' }
      '01021':
        '1943-01-01':
          état: { statut: COM, name: Ars }
        '1956-10-19':
          évts: { changeDeNomPour: Ars-sur-Formans }
          état: { statut: COM, name: Ars-sur-Formans }
      '01022':
        '1943-01-01':
          état: { statut: COM, name: Artemare }
      '01023':
        '1943-01-01':
          état: { statut: COM, name: Asnières-sur-Saône }
      '01024':
        '1943-01-01':
          état: { statut: COM, name: Attignat }
      '01025':
        '1943-01-01':
          état: { statut: COM, name: Bâgé-la-Ville }
        '2018-01-01':
          évts: { prendPourDéléguées: ['01025', '01144'] }
          état: { statut: COM, name: Bâgé-Dommartin, nomCommeDéléguée: Bâgé-la-Ville }
          erat: ['01144']
      '01026':
        '1943-01-01':
          état: { statut: COM, name: Bâgé-le-Châtel }
      '01027':
        '1943-01-01':
          état: { statut: COM, name: Balan }
      '01028':
        '1943-01-01':
          état: { statut: COM, name: Baneins }
      '01029':
        '1943-01-01':
          état: { statut: COM, name: Beaupont }
      '01030':
        '1943-01-01':
          état: { statut: COM, name: Beauregard }
      '01031':
        '1943-01-01':
          état: { statut: COM, name: Bélignat }
        '1957-08-22':
          évts: { changeDeNomPour: Bellignat }
          état: { statut: COM, name: Bellignat }
      '01032':
        '1943-01-01':
          état: { statut: COM, name: Béligneux }
      '01033':
        '1943-01-01':
          état: { statut: COM, name: Bellegarde }
        '1956-10-19':
          évts: { changeDeNomPour: Bellegarde-sur-Valserine }
          état: { statut: COM, name: Bellegarde-sur-Valserine }
        '1966-03-23':
          évts: { absorbe: ['01126'] }
          état: { statut: COM, name: Bellegarde-sur-Valserine }
        '1971-01-01':
          évts: { absorbe: ['01018'] }
          état: { statut: COM, name: Bellegarde-sur-Valserine }
        '2019-01-01':
          évts: { prendPourDéléguées: ['01033', '01091', '01205'] }
          état: { statut: COM, name: Valserhône, nomCommeDéléguée: Bellegarde-sur-Valserine }
          erat: ['01091', '01205']
      '01034':
        '1943-01-01':
          état: { statut: COM, name: Belley }
      '01035':
        '1943-01-01':
          état: { statut: COM, name: Belleydoux }
      '01036':
        '1943-01-01':
          état: { statut: COM, name: Belmont }
        '1974-11-01':
          évts: { associe: ['01226'] }
          état: { statut: COM, name: Belmont-Luthézieu }
          erat: ['01226']
        '1997-12-01':
          évts: { absorbe: ['01226'] }
          état: { statut: COM, name: Belmont-Luthézieu }
        '2019-01-01':
          évts: { prendPourDéléguées: ['01036', '01221', '01414', '01442'] }
          état: { statut: COM, name: Valromey-sur-Séran, nomCommeDéléguée: Belmont-Luthézieu }
          erat: ['01221', '01414', '01442']
      '01037':
        '1943-01-01':
          état: { statut: COM, name: Bénonces }
      '01038':
        '1943-01-01':
          état: { statut: COM, name: Bény }
      '01039':
        '1943-01-01':
          état: { statut: COM, name: Béon }
      '01040':
        '1943-01-01':
          état: { statut: COM, name: Béréziat }
      '01041':
        '1943-01-01':
          état: { statut: COM, name: Bettant }
      '01042':
        '1943-01-01':
          état: { statut: COM, name: Bey }
      '01043':
        '1943-01-01':
          état: { statut: COM, name: Beynost }
      '01044':
        '1943-01-01':
          état: { statut: COM, name: Billiat }
      '01045':
        '1943-01-01':
          état: { statut: COM, name: Birieux }
      '01046':
        '1943-01-01':
          état: { statut: COM, name: Biziat }
      '01047':
        '1943-01-01':
          état: { statut: COM, name: Blyes }
      '01048':
        '1943-01-01':
          état: { statut: COM, name: Bohas }
        '1974-01-01':
          évts: { sAssocieA: '01245' }
          état: { statut: COMA, name: Bohas, crat: '01245' }
        '2000-01-01':
          évts: { fusionneDans: '01245' }
      '01049':
        '1943-01-01':
          état: { statut: COM, name: 'La Boisse' }
      '01050':
        '1943-01-01':
          état: { statut: COM, name: Boissey }
      '01051':
        '1943-01-01':
          état: { statut: COM, name: Bolozon }
      '01052':
        '1943-01-01':
          état: { statut: COM, name: Bouligneux }
      '01053':
        '1943-01-01':
          état: { statut: COM, name: Bourg }
        '1955-03-31':
          évts: { changeDeNomPour: Bourg-en-Bresse }
          état: { statut: COM, name: Bourg-en-Bresse }
      '01054':
        '1943-01-01':
          état: { statut: COM, name: Bourg-Saint-Christophe }
      '01055':
        '1943-01-01':
          état: { statut: COM, name: Bouvent }
        '1973-01-01':
          évts: { fusionneDans: '01283' }
      '01056':
        '1943-01-01':
          état: { statut: COM, name: Boyeux-Saint-Jérôme }
      '01057':
        '1943-01-01':
          état: { statut: COM, name: Boz }
      '01058':
        '1943-01-01':
          état: { statut: COM, name: Brégnier-Cordon }
      '01059':
        '1943-01-01':
          état: { statut: COM, name: Brénaz }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01453' }
          état: { statut: COMD, name: Brénaz, crat: '01453' }
      '01060':
        '1943-01-01':
          état: { statut: COM, name: Brénod }
      '01061':
        '1943-01-01':
          état: { statut: COM, name: Brens }
      '01062':
        '1943-01-01':
          état: { statut: COM, name: Bressolles }
      '01063':
        '1943-01-01':
          état: { statut: COM, name: Brion }
      '01064':
        '1943-01-01':
          état: { statut: COM, name: Briord }
      '01065':
        '1943-01-01':
          état: { statut: COM, name: Buellas }
      '01066':
        '1943-01-01':
          état: { statut: COM, name: 'La Burbanche' }
      '01067':
        '1943-01-01':
          état: { statut: COM, name: Ceignes }
      '01068':
        '1943-01-01':
          état: { statut: COM, name: Cerdon }
      '01069':
        '1943-01-01':
          état: { statut: COM, name: Certines }
      '01070':
        '1943-01-01':
          état: { statut: COM, name: Cesseins }
        '1974-01-01':
          évts: { sAssocieA: '01165' }
          état: { statut: COMA, name: Cesseins, crat: '01165' }
        '1983-01-01':
          évts: { resteRattachéeA: '01165' }
          état: { statut: COMA, name: Cesseins, crat: '01165' }
        '1996-08-01':
          évts: { fusionneDans: '01165' }
      '01071':
        '1943-01-01':
          état: { statut: COM, name: Cessy }
      '01072':
        '1943-01-01':
          état: { statut: COM, name: Ceyzériat }
      '01073':
        '1943-01-01':
          état: { statut: COM, name: Ceyzérieu }
      '01074':
        '1943-01-01':
          état: { statut: COM, name: Chalamont }
      '01075':
        '1943-01-01':
          état: { statut: COM, name: Chaleins }
      '01076':
        '1943-01-01':
          état: { statut: COM, name: Chaley }
      '01077':
        '1943-01-01':
          état: { statut: COM, name: Challes }
        '2006-07-09':
          évts: { changeDeNomPour: Challes-la-Montagne }
          état: { statut: COM, name: Challes-la-Montagne }
      '01078':
        '1943-01-01':
          état: { statut: COM, name: Challex }
      '01079':
        '1943-01-01':
          état: { statut: COM, name: Champagne }
        '1956-10-19':
          évts: { changeDeNomPour: Champagne-en-Valromey }
          état: { statut: COM, name: Champagne-en-Valromey }
        '1973-01-01':
          évts: { associe: ['01217', '01287'] }
          état: { statut: COM, name: Champagne-en-Valromey }
          erat: ['01217', '01287']
        '1997-01-01':
          évts: { absorbe: ['01217', '01287'] }
          état: { statut: COM, name: Champagne-en-Valromey }
      '01080':
        '1943-01-01':
          état: { statut: COM, name: Champdor }
        '2016-01-01':
          évts: { prendPourDéléguées: ['01080', '01119'] }
          état: { statut: COM, name: Champdor-Corcelles, nomCommeDéléguée: Champdor }
          erat: ['01119']
      '01081':
        '1943-01-01':
          état: { statut: COM, name: Champfromier }
      '01082':
        '1943-01-01':
          état: { statut: COM, name: Chanay }
      '01083':
        '1943-01-01':
          état: { statut: COM, name: Chaneins }
      '01084':
        '1943-01-01':
          état: { statut: COM, name: Chanoz-Châtenay }
      '01085':
        '1943-01-01':
          état: { statut: COM, name: 'La Chapelle-du-Châtelard' }
      '01086':
        '1943-01-01':
          état: { statut: COM, name: Charancin }
        '1974-01-01':
          évts: { sAssocieA: '01414' }
          état: { statut: COMA, name: Charancin, crat: '01414' }
        '1994-02-01':
          évts: { fusionneDans: '01414' }
      '01087':
        '1943-01-01':
          état: { statut: COM, name: Charix }
      '01088':
        '1943-01-01':
          état: { statut: COM, name: Charnoz }
        '1991-03-21':
          évts: { changeDeNomPour: Charnoz-sur-Ain }
          état: { statut: COM, name: Charnoz-sur-Ain }
      '01089':
        '1943-01-01':
          état: { statut: COM, name: Château-Gaillard }
      '01090':
        '1943-01-01':
          état: { statut: COM, name: Châtenay }
      '01091':
        '1943-01-01':
          état: { statut: COM, name: Châtillon-de-Michaille }
        '1973-11-01':
          évts: { associe: ['01278', '01458'] }
          état: { statut: COM, name: Châtillon-en-Michaille }
          erat: ['01278', '01458']
        '1985-02-01':
          évts: { absorbe: ['01278', '01458'] }
          état: { statut: COM, name: Châtillon-en-Michaille }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01033' }
          état: { statut: COMD, name: Châtillon-en-Michaille, crat: '01033' }
      '01092':
        '1943-01-01':
          état: { statut: COM, name: Châtillon-la-Palud }
      '01093':
        '1943-01-01':
          état: { statut: COM, name: Châtillon-sur-Chalaronne }
      '01094':
        '1943-01-01':
          état: { statut: COM, name: Chavannes-sur-Reyssouze }
      '01095':
        '1943-01-01':
          état: { statut: COM, name: Chavannes-sur-Suran }
        '2017-01-01':
          évts: { prendPourDéléguées: ['01095', '01172'] }
          état: { statut: COM, name: 'Nivigne et Suran', nomCommeDéléguée: Chavannes-sur-Suran }
          erat: ['01172']
      '01096':
        '1943-01-01':
          état: { statut: COM, name: Chaveyriat }
      '01097':
        '1943-01-01':
          état: { statut: COM, name: Chavornay }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01453' }
          état: { statut: COMD, name: Chavornay, crat: '01453' }
      '01098':
        '1943-01-01':
          état: { statut: COM, name: Chazey-Bons }
        '2017-01-01':
          évts: { prendPourDéléguées: ['01098', '01316'] }
          état: { statut: COM, name: Chazey-Bons, nomCommeDéléguée: Chazey-Bons }
          erat: ['01316']
      '01099':
        '1943-01-01':
          état: { statut: COM, name: Chazey-sur-Ain }
      '01100':
        '1943-01-01':
          état: { statut: COM, name: Cheignieu-la-Balme }
      '01101':
        '1943-01-01':
          état: { statut: COM, name: Chevillard }
      '01102':
        '1943-01-01':
          état: { statut: COM, name: Chevroux }
      '01103':
        '1943-01-01':
          état: { statut: COM, name: Chevry }
      '01104':
        '1943-01-01':
          état: { statut: COM, name: Chézery }
        '1962-08-27':
          évts: { absorbe: ['01164'] }
          état: { statut: COM, name: Chézery-Forens }
      '01105':
        '1943-01-01':
          état: { statut: COM, name: Civrieux }
      '01106':
        '1943-01-01':
          état: { statut: COM, name: Cize }
      '01107':
        '1943-01-01':
          état: { statut: COM, name: Cleyzieu }
      '01108':
        '1943-01-01':
          état: { statut: COM, name: Coligny }
      '01109':
        '1943-01-01':
          état: { statut: COM, name: Collonges }
      '01110':
        '1943-01-01':
          état: { statut: COM, name: Colomieu }
      '01111':
        '1943-01-01':
          état: { statut: COM, name: Conand }
      '01112':
        '1943-01-01':
          état: { statut: COM, name: Condamine }
      '01113':
        '1943-01-01':
          état: { statut: COM, name: Condeissiat }
      '01114':
        '1943-01-01':
          état: { statut: COM, name: Confort }
      '01115':
        '1943-01-01':
          état: { statut: COM, name: Confrançon }
      '01116':
        '1943-01-01':
          état: { statut: COM, name: Contrevoz }
      '01117':
        '1943-01-01':
          état: { statut: COM, name: Conzieu }
      '01118':
        '1943-01-01':
          état: { statut: COM, name: Corbonod }
      '01119':
        '1943-01-01':
          état: { statut: COM, name: Corcelles }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01080' }
          état: { statut: COMD, name: Corcelles, crat: '01080' }
      '01120':
        '1943-01-01':
          état: { statut: COM, name: Cordieux }
        '1973-01-01':
          évts: { sAssocieA: '01262' }
          état: { statut: COMA, name: Cordieux, crat: '01262' }
      '01121':
        '1943-01-01':
          état: { statut: COM, name: Corlier }
      '01122':
        '1943-01-01':
          état: { statut: COM, name: Cormaranche-en-Bugey }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01185' }
          état: { statut: COMD, name: Cormaranche-en-Bugey, crat: '01185' }
      '01123':
        '1943-01-01':
          état: { statut: COM, name: Cormoranche-sur-Saône }
      '01124':
        '1943-01-01':
          état: { statut: COM, name: Cormoz }
      '01125':
        '1943-01-01':
          état: { statut: COM, name: Corveissiat }
        '1943-08-01':
          évts: { absorbe: ['01377'] }
          état: { statut: COM, name: Corveissiat }
        '1964-06-04':
          évts: { absorbe: ['01020'] }
          état: { statut: COM, name: Corveissiat }
      '01126':
        '1943-01-01':
          état: { statut: COM, name: Coupy }
        '1966-03-23':
          évts: { fusionneDans: '01033' }
      '01127':
        '1943-01-01':
          état: { statut: COM, name: Courmangoux }
      '01128':
        '1943-01-01':
          état: { statut: COM, name: Courtes }
      '01129':
        '1943-01-01':
          état: { statut: COM, name: Crans }
      '01130':
        '1943-01-01':
          état: { statut: COM, name: Cras-sur-Reyssouze }
        '2019-01-01':
          évts: { prendPourDéléguées: ['01130', '01154'] }
          état: { statut: COM, name: 'Bresse Vallons', nomCommeDéléguée: Cras-sur-Reyssouze }
          erat: ['01154']
      '01131':
        '1943-01-01':
          état: { statut: COM, name: Craz }
        '1973-01-01':
          évts: { fusionneDans: '01189' }
      '01132':
        '1943-01-01':
          état: { statut: COM, name: Crépieux-la-Pape }
        '1967-12-31':
          évts: { changeDeCodePour: 69274 }
      '01133':
        '1943-01-01':
          état: { statut: COM, name: Cressin-Rochefort }
      '01134':
        '1943-01-01':
          état: { statut: COM, name: Crottet }
      '01135':
        '1943-01-01':
          état: { statut: COM, name: Crozet }
      '01136':
        '1943-01-01':
          état: { statut: COM, name: Cruzilles-lès-Mépillat }
      '01137':
        '1943-01-01':
          état: { statut: COM, name: Cuisiat }
        '1972-12-01':
          évts: { sAssocieA: '01426' }
          état: { statut: COMA, name: Cuisiat, crat: '01426' }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01426' }
          état: { statut: COMD, name: Cuisiat, crat: '01426' }
      '01138':
        '1943-01-01':
          état: { statut: COM, name: Culoz }
      '01139':
        '1943-01-01':
          état: { statut: COM, name: Curciat-Dongalon }
      '01140':
        '1943-01-01':
          état: { statut: COM, name: Curtafond }
      '01141':
        '1943-01-01':
          état: { statut: COM, name: Cuzieu }
      '01142':
        '1943-01-01':
          état: { statut: COM, name: Dagneux }
      '01143':
        '1943-01-01':
          état: { statut: COM, name: Divonne-les-Bains }
        '1965-02-15':
          évts: { absorbe: ['01438'] }
          état: { statut: COM, name: Divonne-les-Bains }
      '01144':
        '1943-01-01':
          état: { statut: COM, name: Dommartin }
        '2018-01-01':
          évts: { devientDéléguéeDe: '01025' }
          état: { statut: COMD, name: Dommartin, crat: '01025' }
      '01145':
        '1943-01-01':
          état: { statut: COM, name: Dompierre }
        '1954-09-11':
          évts: { changeDeNomPour: Dompierre-sur-Veyle }
          état: { statut: COM, name: Dompierre-sur-Veyle }
      '01146':
        '1943-01-01':
          état: { statut: COM, name: Dompierre-sur-Chalaronne }
      '01147':
        '1943-01-01':
          état: { statut: COM, name: Domsure }
      '01148':
        '1943-01-01':
          état: { statut: COM, name: Dortan }
      '01149':
        '1943-01-01':
          état: { statut: COM, name: Douvres }
      '01150':
        '1943-01-01':
          état: { statut: COM, name: Drom }
      '01151':
        '1943-01-01':
          état: { statut: COM, name: Druillat }
      '01152':
        '1943-01-01':
          état: { statut: COM, name: Échallon }
      '01153':
        '1943-01-01':
          état: { statut: COM, name: Échenevex }
      '01154':
        '1943-01-01':
          état: { statut: COM, name: Étrez }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01130' }
          état: { statut: COMD, name: Étrez, crat: '01130' }
      '01155':
        '1943-01-01':
          état: { statut: COM, name: Évosges }
      '01156':
        '1943-01-01':
          état: { statut: COM, name: Faramans }
      '01157':
        '1943-01-01':
          état: { statut: COM, name: Fareins }
      '01158':
        '1943-01-01':
          état: { statut: COM, name: Farges }
      '01159':
        '1943-01-01':
          état: { statut: COM, name: Feillens }
      '01160':
        '1943-01-01':
          état: { statut: COM, name: Ferney-Voltaire }
      '01161':
        '1943-01-01':
          état: { statut: COM, name: Fitignieu }
        '1974-01-01':
          évts: { sAssocieA: '01414' }
          état: { statut: COMA, name: Fitignieu, crat: '01414' }
        '1994-02-01':
          évts: { fusionneDans: '01414' }
      '01162':
        '1943-01-01':
          état: { statut: COM, name: Flaxieu }
      '01163':
        '1943-01-01':
          état: { statut: COM, name: Foissiat }
      '01164':
        '1943-01-01':
          état: { statut: COM, name: Forens }
        '1962-08-27':
          évts: { fusionneDans: '01104' }
      '01165':
        '1943-01-01':
          état: { statut: COM, name: Francheleins }
        '1974-01-01':
          évts: { associe: ['01003', '01070'] }
          état: { statut: COM, name: Amareins-Francheleins-Cesseins }
          erat: ['01003', '01070']
        '1983-01-01':
          évts: { absorbe: ['01003'] }
          état: { statut: COM, name: Amareins-Francheleins-Cesseins }
          erat: ['01070']
        '1996-08-01':
          évts: { absorbe: ['01070'] }
          état: { statut: COM, name: Amareins-Francheleins-Cesseins }
        '1998-12-09':
          évts: { changeDeNomPour: Francheleins }
          état: { statut: COM, name: Francheleins }
      '01166':
        '1943-01-01':
          état: { statut: COM, name: Frans }
      '01167':
        '1943-01-01':
          état: { statut: COM, name: Garnerans }
      '01168':
        '1943-01-01':
          état: { statut: COM, name: Genay }
        '1967-12-31':
          évts: { changeDeCodePour: 69278 }
      '01169':
        '1943-01-01':
          état: { statut: COM, name: Genouilleux }
      '01170':
        '1943-01-01':
          état: { statut: COM, name: Géovreissiat }
        '2008-10-06':
          évts: { changeDeNomPour: Béard-Géovreissiat }
          état: { statut: COM, name: Béard-Géovreissiat }
      '01171':
        '1943-01-01':
          état: { statut: COM, name: Géovreisset }
      '01172':
        '1943-01-01':
          état: { statut: COM, name: Germagnat }
        '2017-01-01':
          évts: { devientDéléguéeDe: '01095' }
          état: { statut: COMD, name: Germagnat, crat: '01095' }
      '01173':
        '1943-01-01':
          état: { statut: COM, name: Gex }
      '01174':
        '1943-01-01':
          état: { statut: COM, name: Giron }
      '01175':
        '1943-01-01':
          état: { statut: COM, name: Gorrevod }
      '01176':
        '1943-01-01':
          état: { statut: COM, name: 'Le Grand-Abergement' }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01187' }
          état: { statut: COMD, name: 'Le Grand-Abergement', crat: '01187' }
      '01177':
        '1943-01-01':
          état: { statut: COM, name: Grand-Corent }
      '01178':
        '1943-01-01':
          état: { statut: COM, name: Granges }
        '1973-01-01':
          évts: { fusionneDans: '01240' }
      '01179':
        '1943-01-01':
          état: { statut: COM, name: Grièges }
      '01180':
        '1943-01-01':
          état: { statut: COM, name: Grilly }
      '01181':
        '1943-01-01':
          état: { statut: COM, name: Groissiat }
      '01182':
        '1943-01-01':
          état: { statut: COM, name: Groslée }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01338' }
          état: { statut: COMD, name: Groslée, crat: '01338' }
      '01183':
        '1943-01-01':
          état: { statut: COM, name: Guéreins }
      '01184':
        '1943-01-01':
          état: { statut: COM, name: Hautecourt }
        '1973-01-01':
          évts: { associe: ['01327'] }
          état: { statut: COM, name: Hautecourt-Romanèche }
          erat: ['01327']
        '1997-08-01':
          évts: { absorbe: ['01327'] }
          état: { statut: COM, name: Hautecourt-Romanèche }
      '01185':
        '1943-01-01':
          état: { statut: COM, name: Hauteville-Lompnes }
        '1964-09-01':
          évts: { absorbe: ['01201', '01222'] }
          état: { statut: COM, name: Hauteville-Lompnes }
        '2019-01-01':
          évts: { prendPourDéléguées: ['01122', '01185', '01186', '01417'] }
          état: { statut: COM, name: 'Plateau d''Hauteville', nomCommeDéléguée: Hauteville-Lompnes }
          erat: ['01122', '01186', '01417']
      '01186':
        '1943-01-01':
          état: { statut: COM, name: Hostias }
        '2007-08-15':
          évts: { changeDeNomPour: Hostiaz }
          état: { statut: COM, name: Hostiaz }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01185' }
          état: { statut: COMD, name: Hostiaz, crat: '01185' }
      '01187':
        '1943-01-01':
          état: { statut: COM, name: Hotonnes }
        '2016-01-01':
          évts: { prendPourDéléguées: ['01176', '01187', '01292', '01409'] }
          état: { statut: COM, name: 'Haut Valromey', nomCommeDéléguée: Hotonnes }
          erat: ['01176', '01292', '01409']
      '01188':
        '1943-01-01':
          état: { statut: COM, name: Illiat }
      '01189':
        '1943-01-01':
          état: { statut: COM, name: Injoux }
        '1973-01-01':
          évts: { absorbe: ['01131'] }
          état: { statut: COM, name: Injoux-Génissiat }
      '01190':
        '1943-01-01':
          état: { statut: COM, name: Innimond }
      '01191':
        '1943-01-01':
          état: { statut: COM, name: Izenave }
      '01192':
        '1943-01-01':
          état: { statut: COM, name: Izernore }
      '01193':
        '1943-01-01':
          état: { statut: COM, name: Izieu }
      '01194':
        '1943-01-01':
          état: { statut: COM, name: Jassans-Riottier }
      '01195':
        '1943-01-01':
          état: { statut: COM, name: Jasseron }
      '01196':
        '1943-01-01':
          état: { statut: COM, name: Jayat }
      '01197':
        '1943-01-01':
          état: { statut: COM, name: Journans }
      '01198':
        '1943-01-01':
          état: { statut: COM, name: Joyeux }
      '01199':
        '1943-01-01':
          état: { statut: COM, name: Jujurieux }
      '01200':
        '1943-01-01':
          état: { statut: COM, name: Labalme }
      '01201':
        '1943-01-01':
          état: { statut: COM, name: Lacoux }
        '1964-09-01':
          évts: { fusionneDans: '01185' }
      '01202':
        '1943-01-01':
          état: { statut: COM, name: Lagnieu }
        '1965-01-10':
          évts: { absorbe: ['01315'] }
          état: { statut: COM, name: Lagnieu }
      '01203':
        '1943-01-01':
          état: { statut: COM, name: Laiz }
      '01204':
        '1943-01-01':
          état: { statut: COM, name: Lalleyriat }
        '2016-01-01':
          évts: { prendPourDéléguées: ['01204', '01300'] }
          état: { statut: COM, name: 'Le Poizat-Lalleyriat', nomCommeDéléguée: Lalleyriat }
          erat: ['01300']
      '01205':
        '1943-01-01':
          état: { statut: COM, name: Lancrans }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01033' }
          état: { statut: COMD, name: Lancrans, crat: '01033' }
      '01206':
        '1943-01-01':
          état: { statut: COM, name: Lantenay }
      '01207':
        '1943-01-01':
          état: { statut: COM, name: Lapeyrouse }
      '01208':
        '1943-01-01':
          état: { statut: COM, name: Lavours }
      '01209':
        '1943-01-01':
          état: { statut: COM, name: Léaz }
      '01210':
        '1943-01-01':
          état: { statut: COM, name: Lélex }
      '01211':
        '1943-01-01':
          état: { statut: COM, name: Lent }
      '01212':
        '1943-01-01':
          état: { statut: COM, name: Lescheroux }
      '01213':
        '1943-01-01':
          état: { statut: COM, name: Leyment }
      '01214':
        '1943-01-01':
          état: { statut: COM, name: Leyssard }
      '01215':
        '1943-01-01':
          état: { statut: COM, name: Lhôpital }
        '2019-01-01':
          évts: { prendPourDéléguées: ['01215', '01413'] }
          état: { statut: COM, name: Surjoux-Lhopital, nomCommeDéléguée: Lhôpital }
          erat: ['01413']
      '01216':
        '1943-01-01':
          état: { statut: COM, name: Lhuis }
      '01217':
        '1943-01-01':
          état: { statut: COM, name: Lilignod }
        '1973-01-01':
          évts: { sAssocieA: '01079' }
          état: { statut: COMA, name: Lilignod, crat: '01079' }
        '1997-01-01':
          évts: { fusionneDans: '01079' }
      '01218':
        '1943-01-01':
          état: { statut: COM, name: Lochieu }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01453' }
          état: { statut: COMD, name: Lochieu, crat: '01453' }
      '01219':
        '1943-01-01':
          état: { statut: COM, name: Lompnas }
      '01221':
        '1943-01-01':
          état: { statut: COM, name: Lompnieu }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01036' }
          état: { statut: COMD, name: Lompnieu, crat: '01036' }
      '01222':
        '1943-01-01':
          état: { statut: COM, name: Longecombe }
        '1964-09-01':
          évts: { fusionneDans: '01185' }
      '01223':
        '1943-01-01':
          état: { statut: COM, name: Loyes }
        '1974-01-01':
          évts: { sAssocieA: '01450' }
          état: { statut: COMA, name: Loyes, crat: '01450' }
        '1995-01-01':
          évts: { fusionneDans: '01450' }
      '01224':
        '1943-01-01':
          état: { statut: COM, name: Loyettes }
      '01225':
        '1943-01-01':
          état: { statut: COM, name: Lurcy }
      '01226':
        '1943-01-01':
          état: { statut: COM, name: Luthézieu }
        '1974-11-01':
          évts: { sAssocieA: '01036' }
          état: { statut: COMA, name: Luthézieu, crat: '01036' }
        '1997-12-01':
          évts: { fusionneDans: '01036' }
      '01227':
        '1943-01-01':
          état: { statut: COM, name: Magnieu }
        '2019-01-01':
          évts: { prendPourDéléguées: ['01227', '01341'] }
          état: { statut: COM, name: Magnieu, nomCommeDéléguée: Magnieu }
          erat: ['01341']
      '01228':
        '1943-01-01':
          état: { statut: COM, name: Maillat }
      '01229':
        '1943-01-01':
          état: { statut: COM, name: Malafretaz }
      '01230':
        '1943-01-01':
          état: { statut: COM, name: Mantenay-Montlin }
      '01231':
        '1943-01-01':
          état: { statut: COM, name: Manziat }
      '01232':
        '1943-01-01':
          état: { statut: COM, name: Marboz }
      '01233':
        '1943-01-01':
          état: { statut: COM, name: Marchamp }
      '01234':
        '1943-01-01':
          état: { statut: COM, name: Marignieu }
      '01235':
        '1943-01-01':
          état: { statut: COM, name: Marlieux }
      '01236':
        '1943-01-01':
          état: { statut: COM, name: Marsonnas }
      '01237':
        '1943-01-01':
          état: { statut: COM, name: Martignat }
      '01238':
        '1943-01-01':
          état: { statut: COM, name: Massieux }
      '01239':
        '1943-01-01':
          état: { statut: COM, name: Massignieu-de-Rives }
      '01240':
        '1943-01-01':
          état: { statut: COM, name: Matafelon }
        '1973-01-01':
          évts: { absorbe: ['01178'] }
          état: { statut: COM, name: Matafelon-Granges }
      '01241':
        '1943-01-01':
          état: { statut: COM, name: Meillonnas }
      '01242':
        '1943-01-01':
          état: { statut: COM, name: Mérignat }
      '01243':
        '1943-01-01':
          état: { statut: COM, name: Messimy }
        '1983-01-14':
          évts: { changeDeNomPour: Messimy-sur-Saône }
          état: { statut: COM, name: Messimy-sur-Saône }
      '01244':
        '1943-01-01':
          état: { statut: COM, name: Meximieux }
      '01245':
        '1943-01-01':
          état: { statut: COM, name: Meyriat }
        '1974-01-01':
          évts: { associe: ['01048', '01324'] }
          état: { statut: COM, name: Bohas-Meyriat-Rignat }
          erat: ['01048', '01324']
        '2000-01-01':
          évts: { absorbe: ['01048'] }
          état: { statut: COM, name: Bohas-Meyriat-Rignat }
          erat: ['01324']
      '01246':
        '1943-01-01':
          état: { statut: COM, name: Mézériat }
      '01247':
        '1943-01-01':
          état: { statut: COM, name: Mijoux }
      '01248':
        '1943-01-01':
          état: { statut: COM, name: Mionnay }
      '01249':
        '1943-01-01':
          état: { statut: COM, name: Miribel }
      '01250':
        '1943-01-01':
          état: { statut: COM, name: Misérieux }
      '01251':
        '1943-01-01':
          état: { statut: COM, name: Moëns }
        '1975-01-01':
          évts: { fusionneDans: '01313' }
      '01252':
        '1943-01-01':
          état: { statut: COM, name: Mogneneins }
      '01253':
        '1943-01-01':
          état: { statut: COM, name: Mollon }
        '1974-01-01':
          évts: { sAssocieA: '01450' }
          état: { statut: COMA, name: Mollon, crat: '01450' }
        '1995-01-01':
          évts: { fusionneDans: '01450' }
      '01254':
        '1943-01-01':
          état: { statut: COM, name: Montagnat }
      '01255':
        '1943-01-01':
          état: { statut: COM, name: Montagnieu }
      '01256':
        '1943-01-01':
          état: { statut: COM, name: Montanay }
        '1967-12-31':
          évts: { changeDeCodePour: 69284 }
      '01257':
        '1943-01-01':
          état: { statut: COM, name: Montanges }
      '01258':
        '1943-01-01':
          état: { statut: COM, name: Montceaux }
      '01259':
        '1943-01-01':
          état: { statut: COM, name: Montcet }
      '01260':
        '1943-01-01':
          état: { statut: COM, name: 'Le Montellier' }
      '01261':
        '1943-01-01':
          état: { statut: COM, name: Monthieux }
      '01262':
        '1943-01-01':
          état: { statut: COM, name: Montluel }
        '1973-01-01':
          évts: { associe: ['01120'] }
          état: { statut: COM, name: Montluel }
          erat: ['01120']
      '01263':
        '1943-01-01':
          état: { statut: COM, name: Montmerle }
        '1962-05-16':
          évts: { changeDeNomPour: Montmerle-sur-Saône }
          état: { statut: COM, name: Montmerle-sur-Saône }
      '01264':
        '1943-01-01':
          état: { statut: COM, name: Montracol }
      '01265':
        '1943-01-01':
          état: { statut: COM, name: Montréal }
        '1979-12-31':
          évts: { changeDeNomPour: Montréal-la-Cluse }
          état: { statut: COM, name: Montréal-la-Cluse }
      '01266':
        '1943-01-01':
          état: { statut: COM, name: Montrevel }
        '1955-01-29':
          évts: { changeDeNomPour: Montrevel-en-Bresse }
          état: { statut: COM, name: Montrevel-en-Bresse }
      '01267':
        '1943-01-01':
          état: { statut: COM, name: Mornay }
        '1973-03-01':
          évts: { absorbe: ['01455'] }
          état: { statut: COM, name: Nurieux-Volognat }
      '01268':
        '1943-01-01':
          état: { statut: COM, name: Murs-et-Gélignieux }
      '01269':
        '1943-01-01':
          état: { statut: COM, name: Nantua }
      '01270':
        '1943-01-01':
          état: { statut: COM, name: Napt }
        '1974-01-01':
          évts: { fusionneDans: '01410' }
      '01271':
        '1943-01-01':
          état: { statut: COM, name: Nattages }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01286' }
          état: { statut: COMD, name: Nattages, crat: '01286' }
      '01272':
        '1943-01-01':
          état: { statut: COM, name: Neuville-les-Dames }
      '01273':
        '1943-01-01':
          état: { statut: COM, name: Neuville-sur-Ain }
      '01274':
        '1943-01-01':
          état: { statut: COM, name: 'Les Neyrolles' }
      '01275':
        '1943-01-01':
          état: { statut: COM, name: Neyron }
      '01276':
        '1943-01-01':
          état: { statut: COM, name: Niévroz }
      '01277':
        '1943-01-01':
          état: { statut: COM, name: Nivollet-Montgriffon }
      '01278':
        '1943-01-01':
          état: { statut: COM, name: Ochiaz }
        '1973-11-01':
          évts: { sAssocieA: '01091' }
          état: { statut: COMA, name: Ochiaz, crat: '01091' }
        '1985-02-01':
          évts: { fusionneDans: '01091' }
      '01279':
        '1943-01-01':
          état: { statut: COM, name: Oncieu }
      '01280':
        '1943-01-01':
          état: { statut: COM, name: Ordonnaz }
      '01281':
        '1943-01-01':
          état: { statut: COM, name: Ornex }
      '01282':
        '1943-01-01':
          état: { statut: COM, name: Outriaz }
      '01283':
        '1943-01-01':
          état: { statut: COM, name: Oyonnax }
        '1973-01-01':
          évts: { absorbe: ['01055'], associe: ['01440'] }
          état: { statut: COM, name: Oyonnax }
          erat: ['01440']
        '2015-01-01':
          évts: { absorbe: ['01440'] }
          état: { statut: COM, name: Oyonnax }
      '01284':
        '1943-01-01':
          état: { statut: COM, name: Ozan }
      '01285':
        '1943-01-01':
          état: { statut: COM, name: Parcieux }
      '01286':
        '1943-01-01':
          état: { statut: COM, name: Parves }
        '2016-01-01':
          évts: { prendPourDéléguées: ['01271', '01286'] }
          état: { statut: COM, name: 'Parves et Nattages', nomCommeDéléguée: Parves }
          erat: ['01271']
      '01287':
        '1943-01-01':
          état: { statut: COM, name: Passin }
        '1973-01-01':
          évts: { sAssocieA: '01079' }
          état: { statut: COMA, name: Passin, crat: '01079' }
        '1997-01-01':
          évts: { fusionneDans: '01079' }
      '01288':
        '1943-01-01':
          état: { statut: COM, name: Péron }
      '01289':
        '1943-01-01':
          état: { statut: COM, name: Péronnas }
      '01290':
        '1943-01-01':
          état: { statut: COM, name: Pérouges }
      '01291':
        '1943-01-01':
          état: { statut: COM, name: Perrex }
      '01292':
        '1943-01-01':
          état: { statut: COM, name: 'Le Petit-Abergement' }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01187' }
          état: { statut: COMD, name: 'Le Petit-Abergement', crat: '01187' }
      '01293':
        '1943-01-01':
          état: { statut: COM, name: Peyriat }
      '01294':
        '1943-01-01':
          état: { statut: COM, name: Peyrieu }
      '01295':
        '1943-01-01':
          état: { statut: COM, name: Peyzieux }
        '1947-05-21':
          évts: { changeDeNomPour: Peyzieux-sur-Saône }
          état: { statut: COM, name: Peyzieux-sur-Saône }
      '01296':
        '1943-01-01':
          état: { statut: COM, name: Pirajoux }
      '01297':
        '1943-01-01':
          état: { statut: COM, name: Pizay }
      '01298':
        '1943-01-01':
          état: { statut: COM, name: Plagne }
      '01299':
        '1943-01-01':
          état: { statut: COM, name: 'Le Plantay' }
      '01300':
        '1943-01-01':
          état: { statut: COM, name: 'Le Poizat' }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01204' }
          état: { statut: COMD, name: 'Le Poizat', crat: '01204' }
      '01301':
        '1943-01-01':
          état: { statut: COM, name: Polliat }
      '01302':
        '1943-01-01':
          état: { statut: COM, name: Pollieu }
      '01303':
        '1943-01-01':
          état: { statut: COM, name: Poncin }
      '01304':
        '1943-01-01':
          état: { statut: COM, name: 'Pont-d''Ain' }
      '01305':
        '1943-01-01':
          état: { statut: COM, name: Pont-de-Vaux }
      '01306':
        '1943-01-01':
          état: { statut: COM, name: Pont-de-Veyle }
      '01307':
        '1943-01-01':
          état: { statut: COM, name: Port }
      '01308':
        '1943-01-01':
          état: { statut: COM, name: Pougny }
      '01309':
        '1943-01-01':
          état: { statut: COM, name: Pouillat }
      '01310':
        '1943-01-01':
          état: { statut: COM, name: Prémeyzel }
      '01311':
        '1943-01-01':
          état: { statut: COM, name: Prémillieu }
      '01312':
        '1943-01-01':
          état: { statut: COM, name: Pressiat }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01426' }
          état: { statut: COMD, name: Pressiat, crat: '01426' }
      '01313':
        '1943-01-01':
          état: { statut: COM, name: Prévessin }
        '1975-01-01':
          évts: { absorbe: ['01251'] }
          état: { statut: COM, name: Prévessin-Moëns }
      '01314':
        '1943-01-01':
          état: { statut: COM, name: Priay }
      '01315':
        '1943-01-01':
          état: { statut: COM, name: Proulieu }
        '1965-01-10':
          évts: { fusionneDans: '01202' }
      '01316':
        '1943-01-01':
          état: { statut: COM, name: Pugieu }
        '2017-01-01':
          évts: { devientDéléguéeDe: '01098' }
          état: { statut: COMD, name: Pugieu, crat: '01098' }
      '01317':
        '1943-01-01':
          état: { statut: COM, name: Ramasse }
      '01318':
        '1943-01-01':
          état: { statut: COM, name: Rancé }
      '01319':
        '1943-01-01':
          état: { statut: COM, name: Relevant }
      '01320':
        '1943-01-01':
          état: { statut: COM, name: Replonges }
      '01321':
        '1943-01-01':
          état: { statut: COM, name: Revonnas }
      '01322':
        '1943-01-01':
          état: { statut: COM, name: Reyrieux }
      '01323':
        '1943-01-01':
          état: { statut: COM, name: Reyssouze }
      '01324':
        '1943-01-01':
          état: { statut: COM, name: Rignat }
        '1974-01-01':
          évts: { sAssocieA: '01245' }
          état: { statut: COMA, name: Rignat, crat: '01245' }
        '2000-01-01':
          évts: { resteRattachéeA: '01245' }
          état: { statut: COMA, name: Rignat, crat: '01245' }
      '01325':
        '1943-01-01':
          état: { statut: COM, name: Rignieux-le-Franc }
      '01326':
        '1943-01-01':
          état: { statut: COM, name: Rillieux }
        '1967-12-31':
          évts: { changeDeCodePour: 69286 }
      '01327':
        '1943-01-01':
          état: { statut: COM, name: Romanèche }
        '1973-01-01':
          évts: { sAssocieA: '01184' }
          état: { statut: COMA, name: Romanèche, crat: '01184' }
        '1997-08-01':
          évts: { fusionneDans: '01184' }
      '01328':
        '1943-01-01':
          état: { statut: COM, name: Romans }
      '01329':
        '1943-01-01':
          état: { statut: COM, name: Rossillon }
      '01330':
        '1943-01-01':
          état: { statut: COM, name: Ruffieu }
      '01331':
        '1943-01-01':
          état: { statut: COM, name: Saint-Alban }
      '01332':
        '1943-01-01':
          état: { statut: COM, name: Saint-André-de-Bâgé }
      '01333':
        '1943-01-01':
          état: { statut: COM, name: Saint-André-de-Corcy }
      '01334':
        '1943-01-01':
          état: { statut: COM, name: 'Saint-André-d''Huiriat' }
      '01335':
        '1943-01-01':
          état: { statut: COM, name: Saint-André-le-Bouchoux }
      '01336':
        '1943-01-01':
          état: { statut: COM, name: Saint-André-sur-Vieux-Jonc }
      '01337':
        '1943-01-01':
          état: { statut: COM, name: Saint-Bénigne }
      '01338':
        '1943-01-01':
          état: { statut: COM, name: Saint-Benoît }
        '2016-01-01':
          évts: { prendPourDéléguées: ['01182', '01338'] }
          état: { statut: COM, name: Groslée-Saint-Benoit, nomCommeDéléguée: Saint-Benoît }
          erat: ['01182']
      '01339':
        '1943-01-01':
          état: { statut: COM, name: Saint-Bernard }
      '01340':
        '1943-01-01':
          état: { statut: COM, name: Saint-Bois }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01015' }
          état: { statut: COMD, name: Saint-Bois, crat: '01015' }
      '01341':
        '1943-01-01':
          état: { statut: COM, name: Saint-Champ }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01227' }
          état: { statut: COMD, name: Saint-Champ, crat: '01227' }
      '01342':
        '1943-01-01':
          état: { statut: COM, name: Sainte-Croix }
      '01343':
        '1943-01-01':
          état: { statut: COM, name: Saint-Cyr-sur-Menthon }
      '01344':
        '1943-01-01':
          état: { statut: COM, name: Saint-Denis-lès-Bourg }
      '01345':
        '1943-01-01':
          état: { statut: COM, name: Saint-Denis-en-Bugey }
      '01346':
        '1943-01-01':
          état: { statut: COM, name: 'Saint-Didier-d''Aussiat' }
      '01347':
        '1943-01-01':
          état: { statut: COM, name: Saint-Didier-de-Formans }
      '01348':
        '1943-01-01':
          état: { statut: COM, name: Saint-Didier-sur-Chalaronne }
      '01349':
        '1943-01-01':
          état: { statut: COM, name: Saint-Éloi }
      '01350':
        '1943-01-01':
          état: { statut: COM, name: Saint-Étienne-du-Bois }
      '01351':
        '1943-01-01':
          état: { statut: COM, name: Saint-Étienne-sur-Chalaronne }
      '01352':
        '1943-01-01':
          état: { statut: COM, name: Saint-Étienne-sur-Reyssouze }
      '01353':
        '1943-01-01':
          état: { statut: COM, name: Sainte-Euphémie }
      '01354':
        '1943-01-01':
          état: { statut: COM, name: Saint-Genis-Pouilly }
      '01355':
        '1943-01-01':
          état: { statut: COM, name: Saint-Genis-sur-Menthon }
      '01356':
        '1943-01-01':
          état: { statut: COM, name: Saint-Georges-sur-Renon }
      '01357':
        '1943-01-01':
          état: { statut: COM, name: Saint-Germain-de-Joux }
      '01358':
        '1943-01-01':
          état: { statut: COM, name: Saint-Germain-les-Paroisses }
      '01359':
        '1943-01-01':
          état: { statut: COM, name: Saint-Germain-sur-Renon }
      '01360':
        '1943-01-01':
          état: { statut: COM, name: Saint-Jean-de-Gonville }
      '01361':
        '1943-01-01':
          état: { statut: COM, name: Saint-Jean-de-Niost }
      '01362':
        '1943-01-01':
          état: { statut: COM, name: Saint-Jean-de-Thurigneux }
      '01363':
        '1943-01-01':
          état: { statut: COM, name: Saint-Jean-le-Vieux }
      '01364':
        '1943-01-01':
          état: { statut: COM, name: Saint-Jean-sur-Reyssouze }
      '01365':
        '1943-01-01':
          état: { statut: COM, name: Saint-Jean-sur-Veyle }
      '01366':
        '1943-01-01':
          état: { statut: COM, name: Sainte-Julie }
      '01367':
        '1943-01-01':
          état: { statut: COM, name: Saint-Julien-sur-Reyssouze }
      '01368':
        '1943-01-01':
          état: { statut: COM, name: Saint-Julien-sur-Veyle }
      '01369':
        '1943-01-01':
          état: { statut: COM, name: Saint-Just }
      '01370':
        '1943-01-01':
          état: { statut: COM, name: Saint-Laurent }
        '1958-12-19':
          évts: { changeDeNomPour: Saint-Laurent-sur-Saône }
          état: { statut: COM, name: Saint-Laurent-sur-Saône }
      '01371':
        '1943-01-01':
          état: { statut: COM, name: Saint-Marcel }
      '01372':
        '1943-01-01':
          état: { statut: COM, name: Saint-Martin-de-Bavel }
      '01373':
        '1943-01-01':
          état: { statut: COM, name: Saint-Martin-du-Frêne }
      '01374':
        '1943-01-01':
          état: { statut: COM, name: Saint-Martin-du-Mont }
      '01375':
        '1943-01-01':
          état: { statut: COM, name: Saint-Martin-le-Châtel }
      '01376':
        '1943-01-01':
          état: { statut: COM, name: Saint-Maurice-de-Beynost }
      '01377':
        '1943-01-01':
          état: { statut: COM, name: 'Saint-Maurice-d''Échazeaux' }
        '1943-08-01':
          évts: { fusionneDans: '01125' }
      '01378':
        '1943-01-01':
          état: { statut: COM, name: Saint-Maurice-de-Gourdans }
      '01379':
        '1943-01-01':
          état: { statut: COM, name: Saint-Maurice-de-Rémens }
      '01380':
        '1943-01-01':
          état: { statut: COM, name: Saint-Nizier-le-Bouchoux }
      '01381':
        '1943-01-01':
          état: { statut: COM, name: Saint-Nizier-le-Désert }
      '01382':
        '1943-01-01':
          état: { statut: COM, name: Sainte-Olive }
      '01383':
        '1943-01-01':
          état: { statut: COM, name: Saint-Paul-de-Varax }
      '01384':
        '1943-01-01':
          état: { statut: COM, name: Saint-Rambert }
        '1956-10-19':
          évts: { changeDeNomPour: Saint-Rambert-en-Bugey }
          état: { statut: COM, name: Saint-Rambert-en-Bugey }
      '01385':
        '1943-01-01':
          état: { statut: COM, name: Saint-Rémy }
      '01386':
        '1943-01-01':
          état: { statut: COM, name: Saint-Sorlin-en-Bugey }
      '01387':
        '1943-01-01':
          état: { statut: COM, name: Saint-Sulpice }
      '01388':
        '1943-01-01':
          état: { statut: COM, name: Saint-Trivier-de-Courtes }
      '01389':
        '1943-01-01':
          état: { statut: COM, name: Saint-Trivier-sur-Moignans }
      '01390':
        '1943-01-01':
          état: { statut: COM, name: Saint-Vulbas }
      '01391':
        '1943-01-01':
          état: { statut: COM, name: Salavre }
      '01392':
        '1943-01-01':
          état: { statut: COM, name: Samognat }
      '01393':
        '1943-01-01':
          état: { statut: COM, name: Sandrans }
      '01394':
        '1943-01-01':
          état: { statut: COM, name: Sathonay-Camp }
        '1967-12-31':
          évts: { changeDeCodePour: 69292 }
      '01395':
        '1943-01-01':
          état: { statut: COM, name: Sathonay-Village }
        '1967-12-31':
          évts: { changeDeCodePour: 69293 }
      '01396':
        '1943-01-01':
          état: { statut: COM, name: Sault-Brénaz }
      '01397':
        '1943-01-01':
          état: { statut: COM, name: Sauverny }
      '01398':
        '1943-01-01':
          état: { statut: COM, name: Savigneux }
      '01399':
        '1943-01-01':
          état: { statut: COM, name: Ségny }
      '01400':
        '1943-01-01':
          état: { statut: COM, name: Seillonnaz }
      '01401':
        '1943-01-01':
          état: { statut: COM, name: Sergy }
      '01402':
        '1943-01-01':
          état: { statut: COM, name: Sermoyer }
      '01403':
        '1943-01-01':
          état: { statut: COM, name: Serrières }
        '1955-03-31':
          évts: { changeDeNomPour: Serrières-de-Briord }
          état: { statut: COM, name: Serrières-de-Briord }
      '01404':
        '1943-01-01':
          état: { statut: COM, name: Serrières-sur-Ain }
      '01405':
        '1943-01-01':
          état: { statut: COM, name: Servas }
      '01406':
        '1943-01-01':
          état: { statut: COM, name: Servignat }
      '01407':
        '1943-01-01':
          état: { statut: COM, name: Seyssel }
      '01408':
        '1943-01-01':
          état: { statut: COM, name: Simandre }
        '1994-06-13':
          évts: { changeDeNomPour: Simandre-sur-Suran }
          état: { statut: COM, name: Simandre-sur-Suran }
      '01409':
        '1943-01-01':
          état: { statut: COM, name: Songieu }
        '2016-01-01':
          évts: { devientDéléguéeDe: '01187' }
          état: { statut: COMD, name: Songieu, crat: '01187' }
      '01410':
        '1943-01-01':
          état: { statut: COM, name: Sonthonnax-la-Montagne }
        '1974-01-01':
          évts: { absorbe: ['01270'] }
          état: { statut: COM, name: Sonthonnax-la-Montagne }
      '01411':
        '1943-01-01':
          état: { statut: COM, name: Souclin }
      '01412':
        '1943-01-01':
          état: { statut: COM, name: Sulignat }
      '01413':
        '1943-01-01':
          état: { statut: COM, name: Surjoux }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01215' }
          état: { statut: COMD, name: Surjoux, crat: '01215' }
      '01414':
        '1943-01-01':
          état: { statut: COM, name: Sutrieu }
        '1974-01-01':
          évts: { associe: ['01086', '01161'] }
          état: { statut: COM, name: Sutrieu }
          erat: ['01086', '01161']
        '1994-02-01':
          évts: { absorbe: ['01086', '01161'] }
          état: { statut: COM, name: Sutrieu }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01036' }
          état: { statut: COMD, name: Sutrieu, crat: '01036' }
      '01415':
        '1943-01-01':
          état: { statut: COM, name: Talissieu }
      '01416':
        '1943-01-01':
          état: { statut: COM, name: Tenay }
      '01417':
        '1943-01-01':
          état: { statut: COM, name: Thézillieu }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01185' }
          état: { statut: COMD, name: Thézillieu, crat: '01185' }
      '01418':
        '1943-01-01':
          état: { statut: COM, name: Thil }
      '01419':
        '1943-01-01':
          état: { statut: COM, name: Thoiry }
      '01420':
        '1943-01-01':
          état: { statut: COM, name: Thoissey }
      '01421':
        '1943-01-01':
          état: { statut: COM, name: Torcieu }
      '01422':
        '1943-01-01':
          état: { statut: COM, name: Tossiat }
      '01423':
        '1943-01-01':
          état: { statut: COM, name: Toussieux }
      '01424':
        '1943-01-01':
          état: { statut: COM, name: Tramoyes }
      '01425':
        '1943-01-01':
          état: { statut: COM, name: 'La Tranclière' }
      '01426':
        '1943-01-01':
          état: { statut: COM, name: Treffort }
        '1972-12-01':
          évts: { associe: ['01137'] }
          état: { statut: COM, name: Treffort-Cuisiat }
          erat: ['01137']
        '2016-01-01':
          évts: { prendPourDéléguées: ['01137', '01312', '01426'] }
          état: { statut: COM, name: Val-Revermont, nomCommeDéléguée: Treffort }
          erat: ['01137', '01312']
      '01427':
        '1943-01-01':
          état: { statut: COM, name: Trévoux }
      '01428':
        '1943-01-01':
          état: { statut: COM, name: Valeins }
      '01429':
        '1943-01-01':
          état: { statut: COM, name: Vandeins }
      '01430':
        '1943-01-01':
          état: { statut: COM, name: Varambon }
      '01431':
        '1943-01-01':
          état: { statut: COM, name: Vaux-en-Bugey }
      '01432':
        '1943-01-01':
          état: { statut: COM, name: Verjon }
      '01433':
        '1943-01-01':
          état: { statut: COM, name: Vernoux }
      '01434':
        '1943-01-01':
          état: { statut: COM, name: Versailleux }
      '01435':
        '1943-01-01':
          état: { statut: COM, name: Versonnex }
      '01436':
        '1943-01-01':
          état: { statut: COM, name: Vesancy }
      '01437':
        '1943-01-01':
          état: { statut: COM, name: Vescours }
      '01438':
        '1943-01-01':
          état: { statut: COM, name: Vésenex-Crassy }
        '1965-02-15':
          évts: { fusionneDans: '01143' }
      '01439':
        '1943-01-01':
          état: { statut: COM, name: Vésines }
      '01440':
        '1943-01-01':
          état: { statut: COM, name: Veyziat }
        '1973-01-01':
          évts: { sAssocieA: '01283' }
          état: { statut: COMA, name: Veyziat, crat: '01283' }
        '2015-01-01':
          évts: { fusionneDans: '01283' }
      '01441':
        '1943-01-01':
          état: { statut: COM, name: 'Vieu-d''Izenave' }
      '01442':
        '1943-01-01':
          état: { statut: COM, name: Vieu }
        '2019-01-01':
          évts: { devientDéléguéeDe: '01036' }
          état: { statut: COMD, name: Vieu, crat: '01036' }
      '01443':
        '1943-01-01':
          état: { statut: COM, name: Villars }
        '1956-10-19':
          évts: { changeDeNomPour: Villars-les-Dombes }
          état: { statut: COM, name: Villars-les-Dombes }
      '01444':
        '1943-01-01':
          état: { statut: COM, name: Villebois }
      '01445':
        '1943-01-01':
          état: { statut: COM, name: Villemotier }
      '01446':
        '1943-01-01':
          état: { statut: COM, name: Villeneuve }
      '01447':
        '1943-01-01':
          état: { statut: COM, name: Villereversure }
      '01448':
        '1943-01-01':
          état: { statut: COM, name: Villes }
      '01449':
        '1943-01-01':
          état: { statut: COM, name: Villette }
        '1991-03-21':
          évts: { changeDeNomPour: Villette-sur-Ain }
          état: { statut: COM, name: Villette-sur-Ain }
      '01450':
        '1943-01-01':
          état: { statut: COM, name: Villieu }
        '1974-01-01':
          évts: { associe: ['01223', '01253'] }
          état: { statut: COM, name: Villieu-Loyes-Mollon }
          erat: ['01223', '01253']
        '1995-01-01':
          évts: { absorbe: ['01223', '01253'] }
          état: { statut: COM, name: Villieu-Loyes-Mollon }
      '01451':
        '1943-01-01':
          état: { statut: COM, name: Viriat }
      '01452':
        '1943-01-01':
          état: { statut: COM, name: Virieu-le-Grand }
      '01453':
        '1943-01-01':
          état: { statut: COM, name: Virieu-le-Petit }
        '2019-01-01':
          évts: { prendPourDéléguées: ['01059', '01097', '01218', '01453'] }
          état: { statut: COM, name: Arvière-en-Valromey, nomCommeDéléguée: Virieu-le-Petit }
          erat: ['01059', '01097', '01218']
      '01454':
        '1943-01-01':
          état: { statut: COM, name: Virignin }
      '01455':
        '1943-01-01':
          état: { statut: COM, name: Volognat }
        '1973-03-01':
          évts: { fusionneDans: '01267' }
      '01456':
        '1943-01-01':
          état: { statut: COM, name: Vongnes }
      '01457':
        '1943-01-01':
          état: { statut: COM, name: Vonnas }
      '01458':
        '1943-01-01':
          état: { statut: COM, name: Vouvray }
        '1973-11-01':
          évts: { sAssocieA: '01091' }
          état: { statut: COMA, name: Vouvray, crat: '01091' }
        '1985-02-01':
          évts: { fusionneDans: '01091' }


