# Référentiel historique des codes Insee

L'Insee publie l'état du COG au 1er janvier ainsi que les évolutions des communes depuis le 1/1/1943.  
Ce sous-projet consiste à produire un document reprenant ces informations et les restructurant afin d'en faciliter la réutilisation.
Ce document ([disponible ici](histov.yaml)) est structuré dans le [format Yaml](https://fr.wikipedia.org/wiki/YAML) facile à consulter
(par un humain et une machine) et à exploiter (par une machine).
Sa structure est formellement définie par un [schéma JSON](https://json-schema.org/) [disponible ici](exhisto.yaml).

Après avoir défini, dans une première partie, la notion d'évènement,
ce document présente, dans une seconde, la structuration du référentiel historique des codes Insee.
Enfin, un extrait illustre cette structuration.

## Définition des évènements sur les codes Insee

### Liens entre codes Insee et entités administratives

Un code Insee à une date donnée peut correspondre:

- à une commune simple (COMS),
- à une entité rattachée (ER) à une commune simple qui peut être:
  - une commune associée (COMA),
  - une commune déléguée (COMD),
  - un arrondissement municipal (ARDM),
- simultanément à une commune simple et à une commune déléguée, que j'appelle commune mixte (COMM).

Dans la suite le terme *entité* désignera une commune simple ou une entité rattachée.

### Définition des opérations sur les entités et des évènements sur les codes Insee

Les opérations sont exprimées sous la forme d'évènements:

- s'appliquant à un code Insee,
- ayant généralement en paramètres un code Insee ou une liste de codes Insee.

Par exemple, l'opération de fusion de la commune d'Amareins (01003)
dans la commune de Amareins-Francheleins-Cesseins (01165) s'exprime en Yaml par:

    '01003': {fusionneDans: '01165'}

Pour cet évènement de fusion, un évènement `absorbe`, appelé *mirroir*, est défini sur l'objet de l'évènement de fusion
et s'exprime par:

    '01165': {absorbe: ['01003']}

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
  - exemple: `{45117: {seDissoutDans: [45093, 45313]}, 45093: {reçoitUnePartieDe: 45117}, 45313: {reçoitUnePartieDe: 45117}}`
- création d'une entité A par agrégation de morceaux de territoire pris à plusieurs autres entités Bi
  - évènements: `A.crééeAPartirDe.(Bi) /  B.contribueA.A`
  - exemple: `{38567: {crééeAPartirDe: [38422, 38478, 38529] }, 38422: {contribueA: 38567 }}`
- suppression d'une entité A par fusion de son territoire dans celui d'une autre B
  - évènements: `A.fusionneDans.B / B.absorbe.(A)`
  - exemple: `{'01003': {fusionneDans: '01165'}, '01165': {absorbe: ['01003']}}`
  - cas particulier:
    le cas de 2 entités qui fusionnent pour en créer une nouvelle avec un nouveau code Insee est traité par une fusion suivie
    d'un changement de code
- une entité A se scinde en 2 pour en créer une nouvelle B, les évènements définissent le type de l'entités créée
  - évènements: `A.seScindePourCréer.(B) / B.crééeCommeSimpleParScissionDe.A, B.crééeCommeAssociéeParScissionDe.A, B.crééCommeArrondissementMunicipalParScissionDe.A`
  - exemple: `{97414: { seScindePourCréer: [97424] }, 97424: { crééeCommeSimpleParScissionDe: 97414 }}`

#### Les opérations ensemblistes

- une entité A se rattache à (s'associe ou devient déléguée) une COMS B
  - *évènements*: `A.sAssocieA.B / B.prendPourAssociées.(A), A.devientDéléguéeDe.B / B.prendPourDéléguées.(A)`
- une entité A se détache à une COMS B
  - évènements: `A.seDétacheDe.B / B.détacheCommeSimples.(A)`
- une entité A reste attachée à une COMS B
  - évènements: `A.resteAssociéeA.B / B.gardeCommeAssociées.(A), A.resteDéléguéeDe.B / B.gardeCommeDéléguées.(A)`

#### Les autres opérations

- l'entrée d'une entité dans le référentiel est exprimée par une absence d'évènement ;
  l'entrée est généralement effectuée au 1/1/1943 sauf pour les communes de Mayotte, dont l'entrée est datée du 31/3/2011,
  date à laquelle Mayotte est devenu un département francais,
- la sortie du référentiel est exceptionnelle, il s'agit de Saint-Martin et de Saint-Barthélémy 
  - évènement: `A.sortDuPérimètreDuRéférentiel.null`
  - exemple: `{97123: {sortDuPérimètreDuRéférentiel: null }}`
- dans certains cas, une entité peut changer de code, notamment quand elle change de département
  - évènements: A.changeDeCodePour.B / B.avaitPourCode.A
  - exemple: `{2A004: {avaitPourCode: 20004}, 20004: {changeDeCodePour: 2A004}}`
- une entité change de nom pour un autre
  - évènement: `A.changeDeNomPour.NouveauNom`
  - exemple: `{'01053': { changeDeNomPour: Bourg-en-Bresse }}`
- une commune simple peut être modifiée par la modification d'une de ses entités rattachées
  - évènement: `estModifiéeIndirectementPar`

## Structuration de l'historique des codes Insee

Le référentiel historique des codes INSEE des communes est principalement constitué d'un dictionnaire des codes INSEE des communes
simples et des entités rattachées (communes associées, communes déléguées et arrondissements municipaux) ayant existé depuis le
1/1/1943 associant à chaque code des infos versionnées indexées par la date de la version.  
Outre cette date, chaque version correspond à:

- un ou des évènement(s) de création/modification/suppression de la version, sauf pour la version initiale datée du 1/1/1943,
  sauf pour les communes de Mayotte, dont l'état initial est daté du 31/3/2011, date à laquelle Mayotte est devenu un département
  francais,
- l'état résultant du/des évènement(s) de l'entité associée au code, valide à partir de la date de la version jusqu'à la date
  de la version suivante s'il y en a une, sinon valide à la date de validité du référentiel ;
  cet état est absent ssi le(s) évènement(s) conduisent à une suppression de l'entité,
- s'il y en a, la liste des entités rattachées, déduites de l'état de ces entités réttachées.

Certaines informations peuvent être déduites des informations primaires ; cela est alors signalé dans les commentaires du schéma.  
Outre ce dictionnaire défini dans le champs contents, le document contient différentes champs,
notamment des propriétés Dublin Core (`title`, `description`, `created`, `modified`, `valid`)
ainsi qu'un champ `$schema` contenant une référence vers son schema JSON
et un champ `ydADscrBhv` peut être utilisé pour afficher le fichier.

### Les évènements

Les opérations sur les entités sont décrites par des évènements de création/modification/suppression s'appliquant à un code Insee ;
la plupart prennent en objet un code INSEE ou une liste et, dans ce cas, les codes INSEE objets portent à la même un date un évènement
appelé mirroir.  
La définition de ces types d'évènement respecte les principes suivants:

- le nombre de types d'évènements est limité afin de faciliter la compréhension du modèle
- l'état issu d'un évènement doit être défini par l'état précédent ainsi que les infos portées par l'évènement,
  sauf pour les changements de nom,
- le fichier doit permettre à un humain de comprendre facilement l'évolution de(s) l'entité(s) associée(s) à un code Insee

Certains évènements, comme mentionnés dans les commentaires, peuvent être déduits de leurs évènements mirroirs. Lorsque
l'information déduite est absente alors l'objet de l'évènement est une liste vide.

### L'état

Etat résultant des évènements et valide à partir de la date de la version et soit, s'il y a une version suivante, jusqu'à sa
date, soit, sinon, valide à la date de validité du référentiel. Dans le premier cas on dit que la version est périmée, dans
le second qu'il s'agit de la version courante.

### Erat

Erat

## Extrait
L'extrait ci-dessous illustre le contenu du référentiel.


    title: 'Référentiel historique des communes'
    '@id': 'http://id.georef.eu/comhisto/insee/histov'
    description: 'Voir la documentation sur https://github.com/benoitdavidfr/comhisto'
    created: '2020-08-05T10:10:45+00:00'
    valid: '2020-01-01'
    $schema: 'http://id.georef.eu/comhisto/insee/exhisto/$schema'
    ydADscrBhv: ...
    contents:
      '01001':
        '1943-01-01':
          etat: { name: 'L''Abergement-Clémenciat', statut: COMS }
      '01002':
        '1943-01-01':
          etat: { name: 'L''Abergement-de-Varey', statut: COMS }
      '01003':
        '1943-01-01':
          etat: { name: Amareins, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01165' }
          etat: { name: Amareins, statut: COMA, crat: '01165' }
        '1983-01-01':
          evts: { fusionneDans: '01165' }
      '01004':
        '1943-01-01':
          etat: { name: Ambérieu, statut: COMS }
        '1955-03-31':
          evts: { changeDeNomPour: Ambérieu-en-Bugey }
          etat: { name: Ambérieu-en-Bugey, statut: COMS }
      '01015':
        '1943-01-01':
          etat: { name: Arbignieu, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01015', prendPourDéléguées: ['01015', '01340'] }
          etat: { name: 'Arboys en Bugey', statut: COMM, nomCommeDéléguée: Arbignieu }
          erat: { aPourDéléguées: ['01015', '01340'] }
      '01018':
        '1943-01-01':
          etat: { name: Arlod, statut: COMS }
        '1971-01-01':
          evts: { fusionneDans: '01033' }
      '01020':
        '1943-01-01':
          etat: { name: Arnans, statut: COMS }
        '1964-06-04':
          evts: { fusionneDans: '01125' }
      '01021':
        '1943-01-01':
          etat: { name: Ars, statut: COMS }
        '1956-10-19':
          evts: { changeDeNomPour: Ars-sur-Formans }
          etat: { name: Ars-sur-Formans, statut: COMS }
      '01025':
        '1943-01-01':
          etat: { name: Bâgé-la-Ville, statut: COMS }
        '2018-01-01':
          evts: { devientDéléguéeDe: '01025', prendPourDéléguées: ['01025', '01144'] }
          etat: { name: Bâgé-Dommartin, statut: COMM, nomCommeDéléguée: Bâgé-la-Ville }
          erat: { aPourDéléguées: ['01025', '01144'] }
      '01031':
        '1943-01-01':
          etat: { name: Bélignat, statut: COMS }
        '1957-08-22':
          evts: { changeDeNomPour: Bellignat }
          etat: { name: Bellignat, statut: COMS }
      '01033':
        '1943-01-01':
          etat: { name: Bellegarde, statut: COMS }
        '1956-10-19':
          evts: { changeDeNomPour: Bellegarde-sur-Valserine }
          etat: { name: Bellegarde-sur-Valserine, statut: COMS }
        '1966-03-23':
          evts: { absorbe: ['01126'] }
          etat: { name: Bellegarde-sur-Valserine, statut: COMS }
        '1971-01-01':
          evts: { absorbe: ['01018'] }
          etat: { name: Bellegarde-sur-Valserine, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01033', prendPourDéléguées: ['01033', '01091', '01205'] }
          etat: { name: Valserhône, statut: COMM, nomCommeDéléguée: Bellegarde-sur-Valserine }
          erat: { aPourDéléguées: ['01033', '01091', '01205'] }
      '01036':
        '1943-01-01':
          etat: { name: Belmont, statut: COMS }
        '1974-11-01':
          evts: { prendPourAssociées: ['01226'] }
          etat: { name: Belmont-Luthézieu, statut: COMS }
          erat: { aPourAssociées: ['01226'] }
        '1997-12-01':
          evts: { absorbe: ['01226'] }
          etat: { name: Belmont-Luthézieu, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01036', prendPourDéléguées: ['01036', '01221', '01414', '01442'] }
          etat: { name: Valromey-sur-Séran, statut: COMM, nomCommeDéléguée: Belmont-Luthézieu }
          erat: { aPourDéléguées: ['01036', '01221', '01414', '01442'] }
      '01048':
        '1943-01-01':
          etat: { name: Bohas, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01245' }
          etat: { name: Bohas, statut: COMA, crat: '01245' }
        '2000-01-01':
          evts: { fusionneDans: '01245' }
      '01053':
        '1943-01-01':
          etat: { name: Bourg, statut: COMS }
        '1955-03-31':
          evts: { changeDeNomPour: Bourg-en-Bresse }
          etat: { name: Bourg-en-Bresse, statut: COMS }
      '01055':
        '1943-01-01':
          etat: { name: Bouvent, statut: COMS }
        '1973-01-01':
          evts: { fusionneDans: '01283' }
      '01059':
        '1943-01-01':
          etat: { name: Brénaz, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01453' }
          etat: { name: Brénaz, statut: COMD, crat: '01453' }
      '01070':
        '1943-01-01':
          etat: { name: Cesseins, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01165' }
          etat: { name: Cesseins, statut: COMA, crat: '01165' }
        '1983-01-01':
          evts: { resteAssociéeA: '01165' }
          etat: { name: Cesseins, statut: COMA, crat: '01165' }
        '1996-08-01':
          evts: { fusionneDans: '01165' }
      '01077':
        '1943-01-01':
          etat: { name: Challes, statut: COMS }
        '2006-07-09':
          evts: { changeDeNomPour: Challes-la-Montagne }
          etat: { name: Challes-la-Montagne, statut: COMS }
      '01079':
        '1943-01-01':
          etat: { name: Champagne, statut: COMS }
        '1956-10-19':
          evts: { changeDeNomPour: Champagne-en-Valromey }
          etat: { name: Champagne-en-Valromey, statut: COMS }
        '1973-01-01':
          evts: { prendPourAssociées: ['01217', '01287'] }
          etat: { name: Champagne-en-Valromey, statut: COMS }
          erat: { aPourAssociées: ['01217', '01287'] }
        '1997-01-01':
          evts: { absorbe: ['01217', '01287'] }
          etat: { name: Champagne-en-Valromey, statut: COMS }
      '01080':
        '1943-01-01':
          etat: { name: Champdor, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01080', prendPourDéléguées: ['01080', '01119'] }
          etat: { name: Champdor-Corcelles, statut: COMM, nomCommeDéléguée: Champdor }
          erat: { aPourDéléguées: ['01080', '01119'] }
      '01086':
        '1943-01-01':
          etat: { name: Charancin, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01414' }
          etat: { name: Charancin, statut: COMA, crat: '01414' }
        '1994-02-01':
          evts: { fusionneDans: '01414' }
      '01088':
        '1943-01-01':
          etat: { name: Charnoz, statut: COMS }
        '1991-03-21':
          evts: { changeDeNomPour: Charnoz-sur-Ain }
          etat: { name: Charnoz-sur-Ain, statut: COMS }
      '01091':
        '1943-01-01':
          etat: { name: Châtillon-de-Michaille, statut: COMS }
        '1973-11-01':
          evts: { prendPourAssociées: ['01278', '01458'] }
          etat: { name: Châtillon-en-Michaille, statut: COMS }
          erat: { aPourAssociées: ['01278', '01458'] }
        '1985-02-01':
          evts: { absorbe: ['01278', '01458'] }
          etat: { name: Châtillon-en-Michaille, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01033' }
          etat: { name: Châtillon-en-Michaille, statut: COMD, crat: '01033' }
      '01095':
        '1943-01-01':
          etat: { name: Chavannes-sur-Suran, statut: COMS }
        '2017-01-01':
          evts: { devientDéléguéeDe: '01095', prendPourDéléguées: ['01095', '01172'] }
          etat: { name: 'Nivigne et Suran', statut: COMM, nomCommeDéléguée: Chavannes-sur-Suran }
          erat: { aPourDéléguées: ['01095', '01172'] }
      '01097':
        '1943-01-01':
          etat: { name: Chavornay, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01453' }
          etat: { name: Chavornay, statut: COMD, crat: '01453' }
      '01098':
        '1943-01-01':
          etat: { name: Chazey-Bons, statut: COMS }
        '2017-01-01':
          evts: { devientDéléguéeDe: '01098', prendPourDéléguées: ['01098', '01316'] }
          etat: { name: Chazey-Bons, statut: COMM, nomCommeDéléguée: Chazey-Bons }
          erat: { aPourDéléguées: ['01098', '01316'] }
      '01104':
        '1943-01-01':
          etat: { name: Chézery, statut: COMS }
        '1962-08-27':
          evts: { absorbe: ['01164'] }
          etat: { name: Chézery-Forens, statut: COMS }
      '01119':
        '1943-01-01':
          etat: { name: Corcelles, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01080' }
          etat: { name: Corcelles, statut: COMD, crat: '01080' }
      '01120':
        '1943-01-01':
          etat: { name: Cordieux, statut: COMS }
        '1973-01-01':
          evts: { sAssocieA: '01262' }
          etat: { name: Cordieux, statut: COMA, crat: '01262' }
      '01122':
        '1943-01-01':
          etat: { name: Cormaranche-en-Bugey, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01185' }
          etat: { name: Cormaranche-en-Bugey, statut: COMD, crat: '01185' }
      '01125':
        '1943-01-01':
          etat: { name: Corveissiat, statut: COMS }
        '1943-08-01':
          evts: { absorbe: ['01377'] }
          etat: { name: Corveissiat, statut: COMS }
        '1964-06-04':
          evts: { absorbe: ['01020'] }
          etat: { name: Corveissiat, statut: COMS }
      '01126':
        '1943-01-01':
          etat: { name: Coupy, statut: COMS }
        '1966-03-23':
          evts: { fusionneDans: '01033' }
      '01130':
        '1943-01-01':
          etat: { name: Cras-sur-Reyssouze, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01130', prendPourDéléguées: ['01130', '01154'] }
          etat: { name: 'Bresse Vallons', statut: COMM, nomCommeDéléguée: Cras-sur-Reyssouze }
          erat: { aPourDéléguées: ['01130', '01154'] }
      '01131':
        '1943-01-01':
          etat: { name: Craz, statut: COMS }
        '1973-01-01':
          evts: { fusionneDans: '01189' }
      '01132':
        '1943-01-01':
          etat: { name: Crépieux-la-Pape, statut: COMS }
        '1967-12-31':
          evts: { changeDeCodePour: 69274 }
      '01137':
        '1943-01-01':
          etat: { name: Cuisiat, statut: COMS }
        '1972-12-01':
          evts: { sAssocieA: '01426' }
          etat: { name: Cuisiat, statut: COMA, crat: '01426' }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01426' }
          etat: { name: Cuisiat, statut: COMD, crat: '01426' }
      '01143':
        '1943-01-01':
          etat: { name: Divonne-les-Bains, statut: COMS }
        '1965-02-15':
          evts: { absorbe: ['01438'] }
          etat: { name: Divonne-les-Bains, statut: COMS }
      '01144':
        '1943-01-01':
          etat: { name: Dommartin, statut: COMS }
        '2018-01-01':
          evts: { devientDéléguéeDe: '01025' }
          etat: { name: Dommartin, statut: COMD, crat: '01025' }
      '01145':
        '1943-01-01':
          etat: { name: Dompierre, statut: COMS }
        '1954-09-11':
          evts: { changeDeNomPour: Dompierre-sur-Veyle }
          etat: { name: Dompierre-sur-Veyle, statut: COMS }
      '01154':
        '1943-01-01':
          etat: { name: Étrez, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01130' }
          etat: { name: Étrez, statut: COMD, crat: '01130' }
      '01161':
        '1943-01-01':
          etat: { name: Fitignieu, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01414' }
          etat: { name: Fitignieu, statut: COMA, crat: '01414' }
        '1994-02-01':
          evts: { fusionneDans: '01414' }
      '01164':
        '1943-01-01':
          etat: { name: Forens, statut: COMS }
        '1962-08-27':
          evts: { fusionneDans: '01104' }
      '01165':
        '1943-01-01':
          etat: { name: Francheleins, statut: COMS }
        '1974-01-01':
          evts: { prendPourAssociées: ['01003', '01070'] }
          etat: { name: Amareins-Francheleins-Cesseins, statut: COMS }
          erat: { aPourAssociées: ['01003', '01070'] }
        '1983-01-01':
          evts: { absorbe: ['01003'], gardeCommeAssociées: ['01070'] }
          etat: { name: Amareins-Francheleins-Cesseins, statut: COMS }
          erat: { aPourAssociées: ['01070'] }
        '1996-08-01':
          evts: { absorbe: ['01070'] }
          etat: { name: Amareins-Francheleins-Cesseins, statut: COMS }
        '1998-12-09':
          evts: { changeDeNomPour: Francheleins }
          etat: { name: Francheleins, statut: COMS }
      '01168':
        '1943-01-01':
          etat: { name: Genay, statut: COMS }
        '1967-12-31':
          evts: { changeDeCodePour: 69278 }
      '01170':
        '1943-01-01':
          etat: { name: Géovreissiat, statut: COMS }
        '2008-10-06':
          evts: { changeDeNomPour: Béard-Géovreissiat }
          etat: { name: Béard-Géovreissiat, statut: COMS }
      '01172':
        '1943-01-01':
          etat: { name: Germagnat, statut: COMS }
        '2017-01-01':
          evts: { devientDéléguéeDe: '01095' }
          etat: { name: Germagnat, statut: COMD, crat: '01095' }
      '01176':
        '1943-01-01':
          etat: { name: 'Le Grand-Abergement', statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01187' }
          etat: { name: 'Le Grand-Abergement', statut: COMD, crat: '01187' }
      '01178':
        '1943-01-01':
          etat: { name: Granges, statut: COMS }
        '1973-01-01':
          evts: { fusionneDans: '01240' }
      '01182':
        '1943-01-01':
          etat: { name: Groslée, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01338' }
          etat: { name: Groslée, statut: COMD, crat: '01338' }
      '01184':
        '1943-01-01':
          etat: { name: Hautecourt, statut: COMS }
        '1973-01-01':
          evts: { prendPourAssociées: ['01327'] }
          etat: { name: Hautecourt-Romanèche, statut: COMS }
          erat: { aPourAssociées: ['01327'] }
        '1997-08-01':
          evts: { absorbe: ['01327'] }
          etat: { name: Hautecourt-Romanèche, statut: COMS }
      '01185':
        '1943-01-01':
          etat: { name: Hauteville-Lompnes, statut: COMS }
        '1964-09-01':
          evts: { absorbe: ['01201', '01222'] }
          etat: { name: Hauteville-Lompnes, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01185', prendPourDéléguées: ['01122', '01185', '01186', '01417'] }
          etat: { name: 'Plateau d''Hauteville', statut: COMM, nomCommeDéléguée: Hauteville-Lompnes }
          erat: { aPourDéléguées: ['01122', '01185', '01186', '01417'] }
      '01186':
        '1943-01-01':
          etat: { name: Hostias, statut: COMS }
        '2007-08-15':
          evts: { changeDeNomPour: Hostiaz }
          etat: { name: Hostiaz, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01185' }
          etat: { name: Hostiaz, statut: COMD, crat: '01185' }
      '01187':
        '1943-01-01':
          etat: { name: Hotonnes, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01187', prendPourDéléguées: ['01176', '01187', '01292', '01409'] }
          etat: { name: 'Haut Valromey', statut: COMM, nomCommeDéléguée: Hotonnes }
          erat: { aPourDéléguées: ['01176', '01187', '01292', '01409'] }
      '01189':
        '1943-01-01':
          etat: { name: Injoux, statut: COMS }
        '1973-01-01':
          evts: { absorbe: ['01131'] }
          etat: { name: Injoux-Génissiat, statut: COMS }
      '01201':
        '1943-01-01':
          etat: { name: Lacoux, statut: COMS }
        '1964-09-01':
          evts: { fusionneDans: '01185' }
      '01202':
        '1943-01-01':
          etat: { name: Lagnieu, statut: COMS }
        '1965-01-10':
          evts: { absorbe: ['01315'] }
          etat: { name: Lagnieu, statut: COMS }
      '01204':
        '1943-01-01':
          etat: { name: Lalleyriat, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01204', prendPourDéléguées: ['01204', '01300'] }
          etat: { name: 'Le Poizat-Lalleyriat', statut: COMM, nomCommeDéléguée: Lalleyriat }
          erat: { aPourDéléguées: ['01204', '01300'] }
      '01205':
        '1943-01-01':
          etat: { name: Lancrans, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01033' }
          etat: { name: Lancrans, statut: COMD, crat: '01033' }
      '01215':
        '1943-01-01':
          etat: { name: Lhôpital, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01215', prendPourDéléguées: ['01215', '01413'] }
          etat: { name: Surjoux-Lhopital, statut: COMM, nomCommeDéléguée: Lhôpital }
          erat: { aPourDéléguées: ['01215', '01413'] }
      '01216':
        '1943-01-01':
          etat: { name: Lhuis, statut: COMS }
      '01217':
        '1943-01-01':
          etat: { name: Lilignod, statut: COMS }
        '1973-01-01':
          evts: { sAssocieA: '01079' }
          etat: { name: Lilignod, statut: COMA, crat: '01079' }
        '1997-01-01':
          evts: { fusionneDans: '01079' }
      '01218':
        '1943-01-01':
          etat: { name: Lochieu, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01453' }
          etat: { name: Lochieu, statut: COMD, crat: '01453' }
      '01219':
        '1943-01-01':
          etat: { name: Lompnas, statut: COMS }
      '01221':
        '1943-01-01':
          etat: { name: Lompnieu, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01036' }
          etat: { name: Lompnieu, statut: COMD, crat: '01036' }
      '01222':
        '1943-01-01':
          etat: { name: Longecombe, statut: COMS }
        '1964-09-01':
          evts: { fusionneDans: '01185' }
      '01223':
        '1943-01-01':
          etat: { name: Loyes, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01450' }
          etat: { name: Loyes, statut: COMA, crat: '01450' }
        '1995-01-01':
          evts: { fusionneDans: '01450' }
      '01226':
        '1943-01-01':
          etat: { name: Luthézieu, statut: COMS }
        '1974-11-01':
          evts: { sAssocieA: '01036' }
          etat: { name: Luthézieu, statut: COMA, crat: '01036' }
        '1997-12-01':
          evts: { fusionneDans: '01036' }
      '01227':
        '1943-01-01':
          etat: { name: Magnieu, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01227', prendPourDéléguées: ['01227', '01341'] }
          etat: { name: Magnieu, statut: COMM, nomCommeDéléguée: Magnieu }
          erat: { aPourDéléguées: ['01227', '01341'] }
      '01240':
        '1943-01-01':
          etat: { name: Matafelon, statut: COMS }
        '1973-01-01':
          evts: { absorbe: ['01178'] }
          etat: { name: Matafelon-Granges, statut: COMS }
      '01243':
        '1943-01-01':
          etat: { name: Messimy, statut: COMS }
        '1983-01-14':
          evts: { changeDeNomPour: Messimy-sur-Saône }
          etat: { name: Messimy-sur-Saône, statut: COMS }
      '01245':
        '1943-01-01':
          etat: { name: Meyriat, statut: COMS }
        '1974-01-01':
          evts: { prendPourAssociées: ['01048', '01324'] }
          etat: { name: Bohas-Meyriat-Rignat, statut: COMS }
          erat: { aPourAssociées: ['01048', '01324'] }
        '2000-01-01':
          evts: { absorbe: ['01048'], gardeCommeAssociées: ['01324'] }
          etat: { name: Bohas-Meyriat-Rignat, statut: COMS }
          erat: { aPourAssociées: ['01324'] }
      '01251':
        '1943-01-01':
          etat: { name: Moëns, statut: COMS }
        '1975-01-01':
          evts: { fusionneDans: '01313' }
      '01253':
        '1943-01-01':
          etat: { name: Mollon, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01450' }
          etat: { name: Mollon, statut: COMA, crat: '01450' }
        '1995-01-01':
          evts: { fusionneDans: '01450' }
      '01256':
        '1943-01-01':
          etat: { name: Montanay, statut: COMS }
        '1967-12-31':
          evts: { changeDeCodePour: 69284 }
      '01262':
        '1943-01-01':
          etat: { name: Montluel, statut: COMS }
        '1973-01-01':
          evts: { prendPourAssociées: ['01120'] }
          etat: { name: Montluel, statut: COMS }
          erat: { aPourAssociées: ['01120'] }
      '01263':
        '1943-01-01':
          etat: { name: Montmerle, statut: COMS }
        '1962-05-16':
          evts: { changeDeNomPour: Montmerle-sur-Saône }
          etat: { name: Montmerle-sur-Saône, statut: COMS }
      '01265':
        '1943-01-01':
          etat: { name: Montréal, statut: COMS }
        '1979-12-31':
          evts: { changeDeNomPour: Montréal-la-Cluse }
          etat: { name: Montréal-la-Cluse, statut: COMS }
      '01266':
        '1943-01-01':
          etat: { name: Montrevel, statut: COMS }
        '1955-01-29':
          evts: { changeDeNomPour: Montrevel-en-Bresse }
          etat: { name: Montrevel-en-Bresse, statut: COMS }
      '01267':
        '1943-01-01':
          etat: { name: Mornay, statut: COMS }
        '1973-03-01':
          evts: { absorbe: ['01455'] }
          etat: { name: Nurieux-Volognat, statut: COMS }
      '01270':
        '1943-01-01':
          etat: { name: Napt, statut: COMS }
        '1974-01-01':
          evts: { fusionneDans: '01410' }
      '01271':
        '1943-01-01':
          etat: { name: Nattages, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01286' }
          etat: { name: Nattages, statut: COMD, crat: '01286' }
      '01278':
        '1943-01-01':
          etat: { name: Ochiaz, statut: COMS }
        '1973-11-01':
          evts: { sAssocieA: '01091' }
          etat: { name: Ochiaz, statut: COMA, crat: '01091' }
        '1985-02-01':
          evts: { fusionneDans: '01091' }
      '01283':
        '1943-01-01':
          etat: { name: Oyonnax, statut: COMS }
        '1973-01-01':
          evts: { absorbe: ['01055'], prendPourAssociées: ['01440'] }
          etat: { name: Oyonnax, statut: COMS }
          erat: { aPourAssociées: ['01440'] }
        '2015-01-01':
          evts: { absorbe: ['01440'] }
          etat: { name: Oyonnax, statut: COMS }
      '01286':
        '1943-01-01':
          etat: { name: Parves, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01286', prendPourDéléguées: ['01271', '01286'] }
          etat: { name: 'Parves et Nattages', statut: COMM, nomCommeDéléguée: Parves }
          erat: { aPourDéléguées: ['01271', '01286'] }
      '01287':
        '1943-01-01':
          etat: { name: Passin, statut: COMS }
        '1973-01-01':
          evts: { sAssocieA: '01079' }
          etat: { name: Passin, statut: COMA, crat: '01079' }
        '1997-01-01':
          evts: { fusionneDans: '01079' }
      '01292':
        '1943-01-01':
          etat: { name: 'Le Petit-Abergement', statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01187' }
          etat: { name: 'Le Petit-Abergement', statut: COMD, crat: '01187' }
      '01295':
        '1943-01-01':
          etat: { name: Peyzieux, statut: COMS }
        '1947-05-21':
          evts: { changeDeNomPour: Peyzieux-sur-Saône }
          etat: { name: Peyzieux-sur-Saône, statut: COMS }
      '01300':
        '1943-01-01':
          etat: { name: 'Le Poizat', statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01204' }
          etat: { name: 'Le Poizat', statut: COMD, crat: '01204' }
      '01312':
        '1943-01-01':
          etat: { name: Pressiat, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01426' }
          etat: { name: Pressiat, statut: COMD, crat: '01426' }
      '01313':
        '1943-01-01':
          etat: { name: Prévessin, statut: COMS }
        '1975-01-01':
          evts: { absorbe: ['01251'] }
          etat: { name: Prévessin-Moëns, statut: COMS }
      '01315':
        '1943-01-01':
          etat: { name: Proulieu, statut: COMS }
        '1965-01-10':
          evts: { fusionneDans: '01202' }
      '01316':
        '1943-01-01':
          etat: { name: Pugieu, statut: COMS }
        '2017-01-01':
          evts: { devientDéléguéeDe: '01098' }
          etat: { name: Pugieu, statut: COMD, crat: '01098' }
      '01324':
        '1943-01-01':
          etat: { name: Rignat, statut: COMS }
        '1974-01-01':
          evts: { sAssocieA: '01245' }
          etat: { name: Rignat, statut: COMA, crat: '01245' }
        '2000-01-01':
          evts: { resteAssociéeA: '01245' }
          etat: { name: Rignat, statut: COMA, crat: '01245' }
      '01326':
        '1943-01-01':
          etat: { name: Rillieux, statut: COMS }
        '1967-12-31':
          evts: { changeDeCodePour: 69286 }
      '01327':
        '1943-01-01':
          etat: { name: Romanèche, statut: COMS }
        '1973-01-01':
          evts: { sAssocieA: '01184' }
          etat: { name: Romanèche, statut: COMA, crat: '01184' }
        '1997-08-01':
          evts: { fusionneDans: '01184' }
      '01338':
        '1943-01-01':
          etat: { name: Saint-Benoît, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01338', prendPourDéléguées: ['01182', '01338'] }
          etat: { name: Groslée-Saint-Benoit, statut: COMM, nomCommeDéléguée: Saint-Benoît }
          erat: { aPourDéléguées: ['01182', '01338'] }
      '01340':
        '1943-01-01':
          etat: { name: Saint-Bois, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01015' }
          etat: { name: Saint-Bois, statut: COMD, crat: '01015' }
      '01341':
        '1943-01-01':
          etat: { name: Saint-Champ, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01227' }
          etat: { name: Saint-Champ, statut: COMD, crat: '01227' }
      '01370':
        '1943-01-01':
          etat: { name: Saint-Laurent, statut: COMS }
        '1958-12-19':
          evts: { changeDeNomPour: Saint-Laurent-sur-Saône }
          etat: { name: Saint-Laurent-sur-Saône, statut: COMS }
      '01377':
        '1943-01-01':
          etat: { name: 'Saint-Maurice-d''Échazeaux', statut: COMS }
        '1943-08-01':
          evts: { fusionneDans: '01125' }
      '01384':
        '1943-01-01':
          etat: { name: Saint-Rambert, statut: COMS }
        '1956-10-19':
          evts: { changeDeNomPour: Saint-Rambert-en-Bugey }
          etat: { name: Saint-Rambert-en-Bugey, statut: COMS }
      '01394':
        '1943-01-01':
          etat: { name: Sathonay-Camp, statut: COMS }
        '1967-12-31':
          evts: { changeDeCodePour: 69292 }
      '01395':
        '1943-01-01':
          etat: { name: Sathonay-Village, statut: COMS }
        '1967-12-31':
          evts: { changeDeCodePour: 69293 }
      '01403':
        '1943-01-01':
          etat: { name: Serrières, statut: COMS }
        '1955-03-31':
          evts: { changeDeNomPour: Serrières-de-Briord }
          etat: { name: Serrières-de-Briord, statut: COMS }
      '01408':
        '1943-01-01':
          etat: { name: Simandre, statut: COMS }
        '1994-06-13':
          evts: { changeDeNomPour: Simandre-sur-Suran }
          etat: { name: Simandre-sur-Suran, statut: COMS }
      '01409':
        '1943-01-01':
          etat: { name: Songieu, statut: COMS }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01187' }
          etat: { name: Songieu, statut: COMD, crat: '01187' }
      '01410':
        '1943-01-01':
          etat: { name: Sonthonnax-la-Montagne, statut: COMS }
        '1974-01-01':
          evts: { absorbe: ['01270'] }
          etat: { name: Sonthonnax-la-Montagne, statut: COMS }
      '01413':
        '1943-01-01':
          etat: { name: Surjoux, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01215' }
          etat: { name: Surjoux, statut: COMD, crat: '01215' }
      '01414':
        '1943-01-01':
          etat: { name: Sutrieu, statut: COMS }
        '1974-01-01':
          evts: { prendPourAssociées: ['01086', '01161'] }
          etat: { name: Sutrieu, statut: COMS }
          erat: { aPourAssociées: ['01086', '01161'] }
        '1994-02-01':
          evts: { absorbe: ['01086', '01161'] }
          etat: { name: Sutrieu, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01036' }
          etat: { name: Sutrieu, statut: COMD, crat: '01036' }
      '01417':
        '1943-01-01':
          etat: { name: Thézillieu, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01185' }
          etat: { name: Thézillieu, statut: COMD, crat: '01185' }
      '01426':
        '1943-01-01':
          etat: { name: Treffort, statut: COMS }
        '1972-12-01':
          evts: { prendPourAssociées: ['01137'] }
          etat: { name: Treffort-Cuisiat, statut: COMS }
          erat: { aPourAssociées: ['01137'] }
        '2016-01-01':
          evts: { devientDéléguéeDe: '01426', prendPourDéléguées: ['01137', '01312', '01426'] }
          etat: { name: Val-Revermont, statut: COMM, nomCommeDéléguée: Treffort }
          erat: { aPourDéléguées: ['01137', '01312', '01426'] }
      '01438':
        '1943-01-01':
          etat: { name: Vésenex-Crassy, statut: COMS }
        '1965-02-15':
          evts: { fusionneDans: '01143' }
      '01440':
        '1943-01-01':
          etat: { name: Veyziat, statut: COMS }
        '1973-01-01':
          evts: { sAssocieA: '01283' }
          etat: { name: Veyziat, statut: COMA, crat: '01283' }
        '2015-01-01':
          evts: { fusionneDans: '01283' }
      '01442':
        '1943-01-01':
          etat: { name: Vieu, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01036' }
          etat: { name: Vieu, statut: COMD, crat: '01036' }
      '01443':
        '1943-01-01':
          etat: { name: Villars, statut: COMS }
        '1956-10-19':
          evts: { changeDeNomPour: Villars-les-Dombes }
          etat: { name: Villars-les-Dombes, statut: COMS }
      '01449':
        '1943-01-01':
          etat: { name: Villette, statut: COMS }
        '1991-03-21':
          evts: { changeDeNomPour: Villette-sur-Ain }
          etat: { name: Villette-sur-Ain, statut: COMS }
      '01450':
        '1943-01-01':
          etat: { name: Villieu, statut: COMS }
        '1974-01-01':
          evts: { prendPourAssociées: ['01223', '01253'] }
          etat: { name: Villieu-Loyes-Mollon, statut: COMS }
          erat: { aPourAssociées: ['01223', '01253'] }
        '1995-01-01':
          evts: { absorbe: ['01223', '01253'] }
          etat: { name: Villieu-Loyes-Mollon, statut: COMS }
      '01453':
        '1943-01-01':
          etat: { name: Virieu-le-Petit, statut: COMS }
        '2019-01-01':
          evts: { devientDéléguéeDe: '01453', prendPourDéléguées: ['01059', '01097', '01218', '01453'] }
          etat: { name: Arvière-en-Valromey, statut: COMM, nomCommeDéléguée: Virieu-le-Petit }
          erat: { aPourDéléguées: ['01059', '01097', '01218', '01453'] }
      '01455':
        '1943-01-01':
          etat: { name: Volognat, statut: COMS }
        '1973-03-01':
          evts: { fusionneDans: '01267' }
      '01458':
        '1943-01-01':
          etat: { name: Vouvray, statut: COMS }
        '1973-11-01':
          evts: { sAssocieA: '01091' }
          etat: { name: Vouvray, statut: COMA, crat: '01091' }
        '1985-02-01':
          evts: { fusionneDans: '01091' }

