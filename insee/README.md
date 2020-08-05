# Fichier historique des codes Insee

L'Insee publie l'état du COG au 1er janvier ainsi que les évolutions des communes depuis le 1/1/1943.  
Ce sous-projet consiste à produire un document reprenant ces informations et les restructurant afin d'en faciliter la réutilisation.
Ce document ([disponible ici](histov.yaml)) est structuré dans le [format Yaml](https://fr.wikipedia.org/wiki/YAML) facile à consulter
(par un humain et une machine) et à exploiter (par une machine).
Sa structure est définie formellement par un [schéma JSON](https://json-schema.org/) [disponible ici](exhisto.yaml).

Ce document dans une première partie présente cette structure et dans une seconde résume la méthode de sa production.

## Structuration du fichier historique des codes Insee

### Distinction entre code Insee et entité administrative

Un code Insee à une date donnée peut correspondre:

- à une commune simple (COMS),
- à une entité rattachée (ER) à une commune simple qui peut être:
  - une commune associée (COMA),
  - une commune déléguée (COMD),
  - un arrondissement municipal (ARDM),
- simultanément à une commune simple et à une commune déléguée, que j'appelle commune mixte (COMM).

Dans la suite le terme *entité* désignera une commune simple ou une entité rattachée.

### Opérations sur les entités

3 catégories d'opérations sur entités sont définis:

- la première correspond aux opérations *topologiques*, dans lesquelles chaque entité est vue comme une zone géométrique,  
  par exemple l'opération de fusion d'une entité A dans une autre B ;
- la seconde correspond aux opérations *ensemblistes* entre entités rattachées et communes simples,
  chaque commune simple étant associée à un ensemble d'entités rattachées,
  par ex. l'opération par laquelle une entité A devient commune déléguée d'une entité B ;
- enfin, la troisième catégorie d'opérations correspondent à une entrée ou une sortie du référentiel
  ou à un changement de code ou de nom d'une entité.
  
Les opérations seront exprimées sous la forme d'évènements:
- s'appliquant à un code Insee,
- avec généralement comme paramètres un code Insee ou une liste de codes Insee.

Par exemple, l'opération de fusion de la commune d'Amareins (01003)
dans la commune de Amareins-Francheleins-Cesseins (01165) s'exprime en Yaml par:

    '01003': {fusionneDans: '01165'}

Pour cet évènement de fusion, un évènement `absorbe`, appelé *mirroir*, est défini sur l'objet de l'évènement de fusion
et s'exprime par:

    '01165': {absorbe: ['01003']}


Les types d'opérations, et les types d'évènements correspondants, sont les suivants:

#### Les opérations topologiques

- dissolution d'une entité A par répartition de son territoire entre plusieurs autres entités préexistantes Bi
  - évènements: `A.seDissoutDans.(Bi),  B.reçoitUnePartieDe.A`
  - exemple: `{45117: {seDissoutDans: [45093, 45313]}, 45093: {reçoitUnePartieDe: 45117}, 45313: {reçoitUnePartieDe: 45117}}`
- création d'une entité A par agrégation de morceaux de territoire pris à plusieurs autres entités Bi
  - évènements: `A.crééeAPartirDe.(Bi),  B.contribueA.A`
  - exemple: `{38567: {crééeAPartirDe: [38422, 38478, 38529] }, 38422: {contribueA: 38567 }}`
- suppression d'une entité A par fusion de son territoire dans celui d'une autre B
  - évènements: `A.fusionneDans.B, B.absorbe.(A)`
  - exemple: `{'01003': {fusionneDans: '01165'}, '01165': {absorbe: ['01003']}}`
  - cas particulier:
    le cas de 2 entités qui fusionnent pour en créer une nouvelle avec un nouveau code Insee est traité par une fusion suivie
    d'un changement de code
- une entité A se scinde en 2 pour en créer une nouvelle B, les évènements définissent le type de l'entités créée
  - évènements: `A.seScindePourCréer.(B), B.crééeCommeSimpleParScissionDe.A, B.crééeCommeAssociéeParScissionDe.A, B.crééCommeArrondissementMunicipalParScissionDe.A`
  - exemple: `{97414: { seScindePourCréer: [97424] }, 97424: { crééeCommeSimpleParScissionDe: 97414 }}`

# SUITE


listeOpérationsEnsemblistes:
  rattache:
    title: une entité A se rattache à une CS B
    comment:
      - si A était rattachée à une autre CS alors elle s'en détache au préalable
      - si A avait des ER alors elles doivent simultanément soit se détacher soit se rattacher à une autre CS
      - si A était double alors son ER est rattachée à B, la CS disparait,
        - les autres ER doivent simultanément soit se détacher soit se rattacher à une autre CS
      - A et B peuvent correspondre au même code
    opérationsElementaires:
      - A.sAssocieA.B
      - B.prendPourAssociées.(A)
      - A.devientDéléguéeDe.B
      - B.prendPourDéléguées.(A)
  détache:
    title: une entité A se détache de sa crat B et devient CS
    opérationsElementaires:
      - A.seDétacheDe.B
      - B.détache.(A)
autres:
  - A.changeDeNomPour.NouveauNom
  - A.sortDuRéférentiel
  - A.changeDeCodePour.B
  - B.avaitPourCode.A

plus:
  - lorsque A est rattachée à B et que A seScinde ou fusionne alors cela modifie B, B doit donc porter un mouvement
  - B.estModifiéeIndirectementPar.A
  
casParticuliers:
  perdRattachementPour:
    situation: A a pour associées B,C,D,E
    opérations:
      - A.perdRattachementPour.B -> A.sAssocieA.B
      - B.prendLeRattachementDe.A -> B.seDétacheDe.A, B.prendPourAssociées.(A,C,D,E)
      - (C,D,E).changeDeRattachementPour.B -> (C,D,E).sAssocieA.B
