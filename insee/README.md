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

#### opérations topologiques

- dissolution d'une entité A par répartition de son territoire entre plusieurs autres entités préexistantes Bi
  - évènements
    - A.seDissoutDans.(Bi)
    - B.reçoitUnePartieDe.A
  - exemples:
        51606: { seDissoutDans: [51235, 51313, 51369] }
        51235: { reçoitUnePartieDe: 51606 }
        51313: { reçoitUnePartieDe: 51606 }
        51369: { reçoitUnePartieDe: 51606 }
    
- création d'une entité A par agrégation de morceaux de territoire pris à plusieurs autres entités Bi
  - A.crééeAPartirDe.(Bi)
  - B.contribueA.A
- suppression d'une entité A par fusion de son territoire dans celui d'une autre B
  - A.fusionneDans.B
  - B.absorbe.(A)
- fusionDe2EntitésPourEnCréerUneNouvelle:
    title: 2 entités fusionnent pour en créer une nouvelle qui prend un code Insee différent de ceux des 2 entités fusionnées
    comment: peut être formalisée comme fusionDuneEntitéDansUneAutre + changementDeCode
- 1 entité A se scinde en 2 pour en créer une nouvelle B, les mouvements définissent le type de l'entités créée
  - A.seScindePourCréer.(B)
  - B.crééeCommeSimpleParScissionDe.A
  - B.crééeCommeAssociéeParScissionDe.A
  - B.crééCommeArrondissementMunicipalParScissionDe.A

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

exhisto(avant20200727):
  evts:
    description: |
      évènements de création/modification/suppression, la plupart des évènements prennent en paramètres un code INSEE ou une liste.
      Dans ce cas les codes INSEE ciblés portent à la même un date un évènement appelé mirroir.
    type: object
    additionalProperties: false
    properties:
      changeDeNomPour:
        description: commune changeant de nom avec indication du nouveau nom (pas d'evt. mirroir)
        type: string
      sortDuPérimètreDuRéférentiel:
        description: commune sortant du périmètre du référentiel, cas de Saint-Martin et Saint-Barthélémy (pas d'évt mirroir)
        type: 'null'
      quitteLeDépartementEtPrendLeCode:
        description: c. changeant d'id lors d'un changement de département avec nouvel id (mirroir arriveDansLeDépartementAvecLeCode)
        $ref: '#/definitions/codeInsee'
      arriveDansLeDépartementAvecLeCode:
        description: c. changeant de département avec précédent id (mirroir quitteLeDépartementEtPrendLeCode)
        $ref: '#/definitions/codeInsee'
      seDissoutDans:
        description: c. supprimée avec ids des c. dans lesquelles son territoire est réparti (mirroir reçoitUnePartieDe)
        $ref: '#/definitions/listeDeCodesInsee'
      reçoitUnePartieDe:
        description: c. recevant une partie du territoire d'une c. supprimée avec l'id de la c. supprimée (mirroir seDissoutDans)
        $ref: '#/definitions/codeInsee'
      contribueA:
        description: commune contribuant à la création d'une commune avec l'id de la c. créée (mirroir crééeAPartirDe)
        $ref: '#/definitions/codeInsee'
      crééeAPartirDe:
        description: c. créée avec liste des id des communes dont provient son territoire (mirroir contribueA)
        $ref: '#/definitions/listeDeCodesInsee'
      seFondDans:
        description: c. absorbée par une c. nouvelle sans c. déléguée avec id de la c. nouvelle (mirroir absorbe)
        $ref: '#/definitions/codeInsee'
      absorbe:
        description: c. nouvelle absorbant des c. (m. seFondDans) ou c. absorbant des c. dans le cas d'une fusion (m. fusionneDans)
        $ref: '#/definitions/listeDeCodesInsee'
      fusionneDans:
        description: |
          c. fusionnant par fusion-association dans une autre commune (mirroir absorbe) ou c. fusionnant pour créer une nouvelle
          commune (mirroir crééeParFusionSimpleDe)
        $ref: '#/definitions/codeInsee'
      crééeParFusionSimpleDe:
        description: |
          c. créée par fusion de c. supprimées avec création d'un nouvel id, avec liste des id des c. supprimées,
          1 seul cas (mirroir fusionneDans)
        $ref: '#/definitions/listeDeCodesInsee'
      crééeCommeSimpleParScissionDe:
        description: c. simple créée par scission d'un c. existante avec id de la c. existante (mirroir seScindePourCréer)
        $ref: '#/definitions/codeInsee'
      seScindePourCréer:
        description: c. simple se scindant pour créer une c. avec id de la c. créée (mirroir crééeCommeSimpleParScissionDe)
        $ref: '#/definitions/listeDeCodesInsee'
      sAssocieA:
        description: c. s'associant par fusion-association à une commune de rattachement (mirroir prendPourAssociées)
        $ref: '#/definitions/codeInsee'
      prendPourAssociées:
        description: c. prenant des associées avec ids des c. associées (mirroir sAssocieA)
        $ref: '#/definitions/listeDeCodesInsee'
      seSépareDe:
        description: c. associées ou déléguées se sépare de sa c. de rattachement et devient c. simple (mirroir détacheCommeSimples)
        $ref: '#/definitions/codeInsee'
      détacheCommeSimples:
        description: la c. de rattachement se sépare certaines de ses c. associées ou déléguées comme c. simples (m. seSépareDe)
        $ref: '#/definitions/listeDeCodesInsee'
      devientDéléguéeDe:
        description: c. devenant déléguée lors de la création/évolution d'une c. nouv. avec id de c. de ratt (m. prendPourDéléguées)
        $ref: '#/definitions/codeInsee'
      prendPourDéléguées:
        description: |
          3 cas:
            - c. prenant comme c. déléguées des c. simples lors de la création/évolution d'une c. nouv. avec ids des c. déléguées
              (mirroir devientDéléguéeDe),
            - c. de ratt. et c. déléguée peuvent avoir même code INSEE, l'évt mirroir est alors lui-même,
            - c. prenant comme c. déléguée des c. déjà associées (mirroir changedAssociéeEnDéléguéeDe)
        $ref: '#/definitions/listeDeCodesInsee'
      changedAssociéeEnDéléguéeDe:
        description: c. associée devient déléguée avec id de la c. de rattachement (mirroir prendPourDéléguées)
        $ref: '#/definitions/codeInsee'
      resteAssociéeA:
        description: c. restant associée à l'occasion d'une modification de l'association (mirroir gardeCommeAssociées)
        $ref: '#/definitions/codeInsee'
      gardeCommeAssociées:
        description: c. associante listant les c. restant associées lors d'une modification de l'association (m. resteAssociéeA)
        $ref: '#/definitions/listeDeCodesInsee'
      resteDéléguéeDe:
        description: c. restant déléguée à l'occasion d'une modification de la commune nouvelle (mirroir gardeCommeDéléguées)
        $ref: '#/definitions/codeInsee'
      gardeCommeDéléguées:
        description: c. nouvelle listant les c. restant déléguées à l'occasion d'une modif. de cette c. nouv. (m. resteDéléguéeDe)
        $ref: '#/definitions/listeDeCodesInsee'
      perdRattachementPour:
        description: la c. de rattachement devient c. rattachée d'une de ses c. rattachées qui est indiquée (m. prendLeRattachementDe)
        $ref: '#/definitions/codeInsee'
      prendLeRattachementDe:
        description: c. rattachée prenant le rtchmt avec en param. les rattachées (m. perdRattachementPour+changeDeRattachementPour)
        $ref: '#/definitions/listeDeCodesInsee'
      changeDeRattachementPour:
        description: c. rattachée changeant de rattachement avec en param. la nouv. c. de rattchmt (m. prendLeRattachementDe)
        $ref: '#/definitions/codeInsee'
      crééCommeArrondissementMunicipalParScissionDe:
        description: arrdt municipal créé avec id de celui dont il est issu (m. seScindePourCréerLesNouveauxArrondissementsMunicipaux)
        $ref: '#/definitions/codeInsee'
      seScindePourCréerLesNouveauxArrondissementsMunicipaux:
        description: arrondissements municipaux créés avec id de ceux créés (m. crééCommeArrondissementMunicipalParScissionDe)
        $ref: '#/definitions/listeDeCodesInsee'
      crééeCommeAssociéeParScissionDe:
        description: c. associée créée avec id de la c. dont elle est issue (mirroir seScindePourCréerLesAssociées)
        $ref: '#/definitions/codeInsee'
      seScindePourCréerLesAssociées:
        description: c. associées créées avec id des c. créées (mirroir crééeCommeAssociéeParScissionDe)
        $ref: '#/definitions/listeDeCodesInsee'
      créationDUneRattachéeParScissionDe:
        description: création d'une e. rattachée vue de la c. simple en indiquant l'e. qui se scinde
        $ref: '#/definitions/codeInsee'
  
nouvHisto:
  evts:
    description: |
      Les opérations sur les entités sont décrites par des évènements de création/modification/suppression s'appliquant
      sur un code Insee ; la plupart prennent en paramètres un code INSEE ou une liste et, dans ce cas, les codes INSEE ciblés
      portent à la même un date un évènement appelé mirroir.
      La définition de ces types d'évènement respecte les principes suivants:
        - restriction du nombre pour faciliter la compréhension du modèle
        - définition de l'état issu d'un évènement par l'état précédent ainsi que les infos portées par l'évènement
        - facilité pour un humain de comprendre l'évolution de l'entité(s) associée(s) à un code Insee
    type: object
    additionalProperties: false
    properties:
      changeDeNomPour:
        description: entité changeant de nom avec indication en paramètre du nouveau nom (pas d'evt. mirroir)
        type: string
      sortDuPérimètreDuRéférentiel:
        description: entité sortant du périmètre du référentiel, cas de Saint-Martin et Saint-Barthélémy (pas d'évt mirroir)
        type: 'null'
      changeDeCodePour:
        description: entité changeant de code, en général lors d'un changement de département, avec nouveau code (mirroir avaitPourCode)
        $ref: '#/definitions/codeInsee'
      avaitPourCode:
        description: nouveau code, en général lors d'un changement de département, avec précédent code (mirroir changeDeCodePour)
        $ref: '#/definitions/codeInsee'
      seDissoutDans:
        description: c. supprimée avec codes des c. dans lesquelles son territoire est réparti (mirroir reçoitUnePartieDe)
        $ref: '#/definitions/listeDeCodesInsee'
      reçoitUnePartieDe:
        description: c. recevant une partie du territoire d'une c. supprimée avec le code de la c. supprimée (mirroir seDissoutDans)
        $ref: '#/definitions/codeInsee'
      crééeAPartirDe:
        description: c. créée avec liste des codes des communes dont provient son territoire (mirroir contribueA)
        $ref: '#/definitions/listeDeCodesInsee'
      contribueA:
        description: commune contribuant à la création d'une commune avec le code de la c. créée (mirroir crééeAPartirDe)
        $ref: '#/definitions/codeInsee'
      fusionneDans:
        description: |
          entité supprimée par fusion de son territoire dans une autre dont le code est en param. (mirroir absorbe)
          La fusion de 2 entités pour en créer une nouvelle est traduit par fusionneDans+changeDeCodePour
        $ref: '#/definitions/codeInsee'
      absorbe:
        description: entité en absorbant d'autres (m. fusionneDans)
        $ref: '#/definitions/listeDeCodesInsee'
      seScindePourCréer:
        description: entité se scindant pour en créer de nouvelles avec leur code (mirroir crééeCommeXXXParScissionDe)
        $ref: '#/definitions/listeDeCodesInsee'
      crééeCommeSimpleParScissionDe:
        description: c. simple créée par scission d'un e. existante avec son code (mirroir seScindePourCréer)
        $ref: '#/definitions/codeInsee'
      crééeCommeAssociéeParScissionDe:
        description: c. associée créée par scission d'un e. existante avec son code (mirroir seScindePourCréer)
        $ref: '#/definitions/codeInsee'
      crééCommeArrondissementMunicipalParScissionDe:
        description: arrdt municipal créé par scission d'un e. existante avec son code (mirroir seScindePourCréer)
        $ref: '#/definitions/codeInsee'
      estModifiéeIndirectementPar:
        description: CS modifiée par un évt intervenant sur ses ER avec code de ces ER (mirroir seScindePourCréer)
        $ref: '#/definitions/listeDeCodesInsee'
      sAssocieA:
        description: |
          Entité s'associe par à une commune de rattachement (mirroir prendPourAssociées)
          La commune de rattachement B doit être une commune simple.
          Si A était rattachée à une autre CS alors elle s'en détache au préalable
          Si A avait des ER alors elles doivent simultanément soit se détacher soit se rattacher à une autre CS
          Si A était mixte alors son ER est rattachée à B et la CS disparait.
        $ref: '#/definitions/codeInsee'
      prendPourAssociées:
        description: c. simple prenant des associées avec code des c. associées (mirroir sAssocieA)
        $ref: '#/definitions/listeDeCodesInsee'
      devientDéléguéeDe:
        description: |
          e. devenant déléguée lors de la création/évolution d'une c. nouv. avec code de cette c. (m. prendPourDéléguées)
          La commune de rattachement B doit être une commune simple.
          Si A était rattachée à une autre CS alors elle s'en détache au préalable
          Si A avait des ER alors elles doivent simultanément soit se détacher soit se rattacher à une autre CS
          Si A était mixte alors son ER est rattachée à B et la CS disparait.
        $ref: '#/definitions/codeInsee'
      prendPourDéléguées:
        description: |
          CS prenant comme déléguées des entités lors de la création/évolution d'une c. nouv. avec codes des nouvelles c. déléguées
          (mirroir devientDéléguéeDe)
          Si la c. de ratt. et la c. déléguée ont même code alors il n'y a pas d'evt devientDéléguéeDe
        $ref: '#/definitions/listeDeCodesInsee'
      seSépareDe:
        description: c. associées ou déléguées se sépare de sa c. de rattachement et devient c. simple (mirroir détacheCommeSimples)
        $ref: '#/definitions/codeInsee'
      détacheCommeSimples:
        description: la c. de rattachement se sépare de certaines de ses c. associées ou déléguées comme c. simples (m. seSépareDe)
        $ref: '#/definitions/listeDeCodesInsee'

      
avant2020-07-24:
  entités:
    - communes simples (CS)
    - entités rattachées (ER)
    - entités (E) = CS union ER
  logique:
    - une entité rattachée est rattachée à une et une seule commune simple
    - la géométrie d'une c. simple inclut celle de ses entités rattachées
  mirroirs:
    arriveDansLeDépartementAvecLeCode/quitteLeDépartementEtPrendLeCode: 904
    reçoitUnePartieDe/seDissoutDans: 14 - B est supprimée et son territoire est réparti dans les Ai
    contribueA/crééeAPartirDe: 21 - B est créée avec un territoire constitué de parties des Ai
    seFondDans/absorbe: 141 - A est supprimée et son territoire est intégré dans B
    fusionneDans:
        absorbe: 890 - A est supprimée et son territoire est intégré dans B
        crééeParFusionSimpleDe: 2 - A est supprimée et B est créée avec un territoire constitué de l'union de celui des Ai
    crééeCommeSimpleParScissionDe/seScindePourCréer: 79
    sAssocieA/prendPourAssociées: 1081
    devientDéléguéeDe/prendPourDéléguées: 1721
    prendPourDéléguées/prendPourDéléguées: 716
    changedAssociéeEnDéléguéeDe/prendPourDéléguées: 6
    resteAssociéeA/gardeCommeAssociées: 93
    resteDéléguéeDe/gardeCommeDéléguées: 69
    crééCommeArrondissementMunicipalParScissionDe/seScindePourCréerLesNouveauxArrondissementsMunicipaux: 2
    crééeCommeAssociéeParScissionDe/seScindePourCréerLesAssociées: 2
    seSépareDe/détacheCommeSimples: 241
  opérations:
    description: |
      Un certain nombre d'opérations sont formalisées du point de vue de l'évolution de chaque code INSEE concerné
    dissolutionDUneCommuneSimple:
      title: dissolution d'une commune simple par répartition de son territoire dans plusieurs autres communes simples préexistantes
      count: 6 communes ont été dissoutes
      direct:
        name: seDissoutDans:
        comment: A(CS->N) est supprimée et son territoire est réparti dans les Bi(CS)
        signature: CS x [CS] -> N
      mirroir:
        name: reçoitUnePartieDe
        comment: A(CS->CS) recoit une partie du territoire de B(CS) qui est supprimée
        signature: CS x CS -> CS
    créationDuneCommuneSimpleAPartirDePlusieursMorceaux:
      direct:
        name: crééeAPartirDe
        comment: A(N->CS) est créée avec un territoire constitué de morceaux des Bi
        signature: N x [CS] -> CS
      mirroir:
        name: contribueA
        comment: A(CS->CS) est amputé d'un morcreau de son territoire transféré à B pour sa création comme CS
        signature: CS X N -> CS
  
    fusionneDans/absorbe:
      défDirect: A:CS est supprimée et son territoire est inclus dans B:CS
      défInverse: A:CS absorbe le territoire de B:CS à sa suppression
  
    fusionnePourCréer/crééeParFusion:
      défDirect: A:CS est supprimée et son territoire est inclus dans B:CS
    
    seRattacheA/rattache:
      défDirect: A:CS devient entité rattachée de B:CS
    
    seDétacheDe/détache:
      défDirect: A:ER de B:CS devient CS
eof: