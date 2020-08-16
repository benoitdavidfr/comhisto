<?php
/*PhpDoc:
name: histo.inc.php
title: histo.inc.php - def. des classes Histo, Version et Evt pour gérer le fichier Histo
screens:
doc: |
  Charge le fichier Histo avec les élts
  Traduit les relations entre versions en relations topologiques entre zones géographiques:
    - sameAs pour identité des zones géographiques entre 2 versions
    - includes(a,b) pour inclusion de b dans a
  Ces relations topologiques permettront dans la classe Zone de construire les zones géographiques
  et les relations d'inclusion entre elles.

  Il existe 6 dissolutions.
  Elles sont assimilées ici à des fusions en définissant une commune principale de dissolution dans Histo::DISSOLUTIONS
journal: |
  13/8/2020:
    - traitement des 6 dissolutions assimilées à des fusions par définition d'une commune principale
    - gestion des absorbtion dans Version::idNonRattachante()
  12/8/2020:
    - correction signalée
  1/8/2020:
    - adaptation au nouveau modèle d'histo
  21/7/2020:
    - reprise à partir de ../../rpicom/rpigeo/bzone.php
*/

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Histo {
  const DISSOLUTIONS = [ // [cinsee se dissolvant => cinsee on considère que la dissolution est effectuée]
    '08227' => '08454', // le hameau de Hocmont (08227) est maintenant sur la commune de Touligny (08454)
    '45117' => '45093', // le hameau Creuzy (45117) est maintenant sur la commune de Chevilly (45093)
    '51606' => '51369', // Verdey (51606) -> Mœurs-Verdey (51369)
    '51385' => '51440', // Moronvilliers (51385) -> Pontfaverger-Moronvilliers (51440)
    '60606' => '60509',
    '77362' => '77444',
  ];
  static $all=[]; // [cinsee => Histo] - tous les Histo par leur code Insee
  protected $cinsee;
  protected $versions=[]; // [ dCreation => Version ] - triées dans l'ordre chronologique
  
  static function load(string $fpath) {
    $yaml = Yaml::parseFile("$fpath.yaml");
    $nbrec=0;
    foreach ($yaml['contents'] as $cinsee => $versions) {
      //echo Yaml::dump([$cinsee => $versions], 3, 2);
      self::$all[$cinsee] = new Histo($cinsee, $versions);
      /*print_r(self::$all[$cinsee]);
      if ($nbrec++ > 20)
        die("Fin sur nbrec=$nbrec\n");*/
    }
  }
  
  function __construct(string $cinsee, array $versions) {
    $this->cinsee = $cinsee;
    $dvs = array_keys($versions);
    foreach ($dvs as $iv => $dv) {
      if (isset($versions[$dv]['etat'])) {
        $nextDv = isset($dvs[$iv+1]) ? $dvs[$iv+1] : null;
        $this->versions[$dv] = new Version($cinsee, $dv, $versions[$dv], $nextDv, $nextDv ? $versions[$nextDv] : []);
      }
    }
  }

  function asArray(): array {
    $array = [];
    foreach ($this->versions as $dv => $version) {
      $array[$dv] = $version->asArray();
    }
    return $array;
  }
  
  // accès à une version par sa date de création
  function version(string $dCreation): ?Version { return $this->versions[$dCreation] ?? null; }

  // accès à une version par sa date de fin
  function versionParDateDeFin(string $dFin): ?Version {
    foreach ($this->versions as $version) {
      if ($version->dFin() == $dFin)
        return $version;
    }
    return null;
  }
  
  // renvoie soit le Histo soit la version en fonction de l'identifiant
  static function get(string $id) {
    $cinsee = substr($id, 1, 5);
    if (!isset(self::$all[$cinsee])) {
      echo "Histo $cinsee n'existe pas";
      throw new Exception("Histo $cinsee n'existe pas");
    }
    if (strlen($id) == 6) {
      return self::$all[$cinsee];
    }
    else {
      $dCreation = substr($id, 7);
      if (!isset(self::$all[$cinsee]->versions[$dCreation])) {
        echo "Version $dCreation du Histo $cinsee n'existe pas\n";
        throw new Exception("get impossible");
      }
      //echo "get($id)->"; print_r(self::$all[$cinsee]->versions[$dCreation]);
      return self::$all[$cinsee]->versions[$dCreation];
    }
  }
  
  // Fabrique ttes les zones
  static function buildAllZones(): void {
    foreach (self::$all as $cinsee => $histo)
      $histo->buildZones();
    
    // Cas particuliers
    Zone::sameAs('s14712@1972-07-01', 's14712@2019-12-31');
    
    Zone::traiteInclusions();
  }
  
  // Fabrique les zones corr. à un Histo
  function buildZones(): void {
    //echo Yaml::dump([$this->cinsee => $this->asArray()], 3, 2);
    
    foreach ($this->versions as $version) {
      $version->buildZones();
    }
    
    $this->testAllerRetourFusionnee();
    $this->testAllerRetourRattachante();
  }
  
  function testAllerRetourFusionnee() {
    /* gère le cas illustré par 27111 de fusion suivie d'un rétablissement
      27111:
        '1943-01-01':
          etat: { name: Bretagnolles, statut: COMS }
        '1943-12-01':
          evts: { fusionneDans: 27078 }
        '1947-12-19':
          evts: { crééeCommeSimpleParScissionDe: 27078 }
          etat: { name: Bretagnolles, statut: COMS }
    */
    $dCreations = array_keys($this->versions);
    foreach ($dCreations as $noVersion => $dCreation) {
      $version = $this->versions[$dCreation];
      if ($version->evtsFin() && ($version->evtsFin()->keys()==['fusionneDans'])) {
        if ($dCreation2 = $dCreations[$noVersion+1] ?? null) {
          $version2 = $this->versions[$dCreation2];
          if ($version2->evtsCreation()->keys()==['crééeCommeSimpleParScissionDe']) {
            //echo "Fusion/rétablissement détectée pour ",$version2->id()," et ",$version->id(),"\n";
            Zone::sameAs($version2->id(), $version->id());
          }
        }
      }
    }
  }
  
  // teste les cas d'aller-retour d'une rattachante et dans ce cas affirme l'égalité avant/après (ajout 12/8)
  // Je ne teste  les AR que par rapport à la V0. J'ai des cas d'AR depuis une version intermédiaire, eg 14712 corrigé à la main
  function testAllerRetourRattachante(): void {
    $erat = []; // entités rattachées
    $efus = []; // entités fusionnées
    //echo "testAllerRetourRattachante $this->cinsee\n";
    $version0 = array_values($this->versions)[0];
    foreach ($this->versions as $version) {
      if (is_null($evtsFin = $version->evtsFin()))
        continue;
      //echo "  evtsFin=$evtsFin\n";
      foreach ($evtsFin->asArray() as $evtKey => $evtObjects) {
        switch ($evtKey) {
          case 'changeDeNomPour': break;

          case 'fusionneDans': break;
          case 'absorbe': $efus = array_merge($efus, $evtObjects); break;
          case 'seScindePourCréer': {
            $newefus = [];
            foreach ($efus as $fus) {
              if (!in_array($fus, $evtObjects))
                $newefus[] = $fus;
            }
            $efus = $newefus;
            if (!$erat && !$efus) {
              Zone::sameAs($version0->id(), $version->next()->id());
            }
            break;
          }
          
          case 'sAssocieA':
          case 'devientDéléguéeDe': break;
          case 'seDétacheDe': break;
          
          case 'prendPourAssociées': 
          case 'prendPourDéléguées': $erat = array_merge($erat, $evtObjects); break;
          case 'détacheCommeSimples': {
            $newerat = [];
            foreach ($erat as $rat) {
              if (!in_array($rat, $evtObjects))
                $newerat[] = $rat;
            }
            $erat = $newerat;
            if (!$erat && !$efus) {
              Zone::sameAs($version0->id(), $version->next()->id());
            }
            break;
          }

          case 'resteAssociéeA': break;
          case 'resteDéléguéeDe': break;
          case 'gardeCommeAssociées': break;
          case 'gardeCommeDéléguées': break;
          
          case 'changeDeCodePour': return;
          case 'reçoitUnePartieDe': return;
          case 'seDissoutDans': return;
          case 'contribueA': return;
          case 'estModifiéeIndirectementPar': return;
          case 'sortDuPérimètreDuRéférentiel': return;
          
          default: throw new Exception("$evtsFin , version=$version");
        }
      }
    }
    //die("die testAllerRetourRattachante");
  }
};

class Version {
  protected $cinsee; // code insee
  protected $dCreation; // date de création
  protected $dFin; // date de fin ssi périmée sinon null
  protected $statut; // statut simplifié - 's' pour simple, 'r' pour rattachée
  protected $crat; // null ssi s sinon code insee de la commune de rattachement
  protected $erat; // liste des entités rattachées
  protected $nom; // nom
  protected $evtsCreation; // evts de création ou null
  protected $evtsFin; // evts de fin : null si version valide, Evts si version périmée
  protected $nomCDeleguee; // si le code et la date correspondent à la fois à une c.s. et à une c.d. alors nom de la c.d.

  function __construct(string $cinsee, string $dCrea, array $record, ?string $nextDCrea, array $nextRecord) {
    $this->cinsee = $cinsee;
    $this->dCreation = $dCrea;
    $this->dFin = $nextDCrea;
    $this->statut = in_array($record['etat']['statut'], ['COMS','COMM']) ? 's' : 'r';
    $this->crat = $record['etat']['crat'] ?? null;
    $this->erat = $record['erat']['aPourDéléguées'] ?? ($record['erat']['aPourAssociées'] ?? []);
    $this->nom = $record['etat']['name'];
    $this->nomCDeleguee = $record['etat']['nomCommeDéléguée'] ?? null;
    $this->evtsCreation = isset($record['evts']) ? new Evts($record['evts']) : null;
    $this->evtsFin = isset($nextRecord['evts']) ? new Evts($nextRecord['evts']) : null;
  }
  
  function dFin(): string { return $this->dFin; }
  function dCreation(): string { return $this->dCreation; }
  function statut(): string { return $this->statut; }
  function evtsFin(): ?Evts { return $this->evtsFin; }
  function evtsCreation(): ?Evts { return $this->evtsCreation; }
  function id(): string { return $this->statut.$this->cinsee.'@'.$this->dCreation; }
  function rid(): string { return 'r'.$this->cinsee.'@'.$this->dCreation; }
  function isValid(): bool { return is_null($this->dFin); }

  // retourne l'id de la version courante ou précédente non ratachante
  // Vérifie de plus si l'evt de fin contient un absorbe des entités rattachées - correction 13/8/2020
  function idNonRattachante(): string {
    if (!$this->erat)
      return $this->id();
    if (in_array('absorbe', $this->evtsFin->keys())) {
      $absorbees = $this->evtsFin->absorbe;
      sort($absorbees);
      $erat = $this->erat;
      sort($erat);
      if ($absorbees == $erat)
        return $this->id();
    }
    if (!($previous = $this->previous()))
      throw new Exception("Erreur dans idNonRattachante() sur ".$this->id());
    return $previous->idNonRattachante();
  }
  
  function asArray(): array {
    $array = [
      'nom'=> $this->nom,
      'statut'=> $this->statut,
    ];
    if ($this->crat)
      $array['crat'] = $this->crat;
    if ($this->nomCDeleguee)
      $array['nomCDeleguee'] = $this->nomCDeleguee;
    //if ($this->evtsCreation)
      //$array['evtsCreation'] = $this->evtsCreation->asArray();
    if ($this->dFin)
      $array['dFin'] = $this->dFin;
    if ($this->evtsFin)
      $array['evtsFin'] = $this->evtsFin->asArray();
    return $array;
  }
  
  function __toString(): string { return json_encode($this->asArray(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
  
  function next(): ?Version { // version suivante dans le temps
    return Histo::$all[$this->cinsee]->version($this->dFin);
  }
  
  function previous(): ?Version { // version précédente dans le temps
    return Histo::$all[$this->cinsee]->versionParDateDeFin($this->dCreation);
  }

  function buildZones(): void { // construit les zones correspondant à une version
    //echo "buildZones() @ $this\n";
    // définition de la relation à la date de création de la version
    if ($this->statut <> 's') { // cas d'une commune rattachée, elle est incluse dans sa rattachante
      if (!($rattachante = Histo::$all[$this->crat]->version($this->dCreation)))
        throw new Exception("Erreur Ratachante $this->crat@$this->dCreation inexistante pour $this");
      Zone::includes($rattachante->id(), $this->id());
    }
    elseif ($this->nomCDeleguee) { // cas particulier où la version représente la cs et la cd
      // la déléguée propre est incluse dans la simple
      Zone::includes($this->id(), 'r'.$this->cinsee.'@'.$this->dCreation);
    }
    else { // commune standard doit être créée si n'intervient jamais dans une inclusion ou un sameAs
      Zone::getOrCreate($this->id());
    }
    
    // définition de la relation entre la version courante et la version qui suit dans le temps
    if (is_null($this->evtsFin)) return;

    echo "traitements keys=",$this->evtsFin,"\n";
    switch ($this->evtsFin->keys()) {
      // Il n'y a pas d'entité suivante
      case ['sortDuPérimètreDuRéférentiel']: break;
      
      // l'entité suivante est identique à l'entité courante
      case ['changeDeNomPour']:
      case ['devientDéléguéeDe']:
      case ['sAssocieA']:
      case ['resteAssociéeA']:
      case ['resteDéléguéeDe']:
      case ['gardeCommeDéléguées']:
      case ['seDétacheDe']:
      case ['seDétacheDe','sAssocieA']: 
      case ['seDétacheDe','devientDéléguéeDe']: {
        Zone::sameAs($this->id(), $this->next()->id());
        break;
      }
      
      // l'entité courante est incluse dans l'entité absorbante
      case ['fusionneDans']: {
        if ($this->statut == 's') {
          $cratId = $this->evtsFin->fusionneDans;
          $crat = Histo::$all[$cratId]->version($this->dFin);
          if (!$crat) {
            $cratPrev = Histo::$all[$cratId]->versionParDateDeFin($this->dFin);
            $nextCode = $cratPrev->evtsFin->changeDeCodePour;
            $crat = Histo::$all[$nextCode]->version($this->dFin);
          }
          Zone::includes($crat->id(), $this->id());
        }
        break;
      }
      
      // J'assimile une dissolution à une fusion dans une des communes définie dans Histo::DISSOLUTION
      /*Proto
      '08227':
        '1943-01-01':
          etat: { name: Hocmont, statut: COMS }
        '1968-03-02':
          evts: { seDissoutDans: ['08203', '08454'] }*/
      case ['seDissoutDans']: {
        if (!isset(Simplif::DISSOLUTIONS[$this->cinsee]))
          throw new Exception("Erreur cinsee de dissolution non définie pour $this->cinsee");
        $cratId = Simplif::DISSOLUTIONS[$this->cinsee];
        $crat = Histo::$all[$cratId]->version($this->dFin);
        Zone::includes($crat->id(), $this->id());
        break;
      }
      
      /*Proto:
      '08203':
        '1943-01-01':
          etat: { name: Guignicourt-sur-Vence, statut: COMS }
        '1968-03-02':
          evts: { reçoitUnePartieDe: '08227' }
          etat: { name: Guignicourt-sur-Vence, statut: COMS }*/
      case ['reçoitUnePartieDe']:
      case ['changeDeNomPour','reçoitUnePartieDe']: {
        $cdissoute = $this->evtsFin->reçoitUnePartieDe;
        if (!isset(Simplif::DISSOLUTIONS[$cdissoute]))
          throw new Exception("Erreur cinsee de dissolution non définie pour $cdissoute");
        if (Simplif::DISSOLUTIONS[$cdissoute] == $this->cinsee)
          // Si le c. courante est la principale commune de dissolution alors elle grossit
          Zone::includes($this->next()->id(), $this->id());
        else
          // Sinon elle est identique
          Zone::sameAs($this->next()->id(), $this->id());
        break;
      }
      
      case ['absorbe']:
      case ['absorbe','changeDeCodePour']:
      case ['absorbe','gardeCommeAssociées']:
      case ['gardeCommeAssociées','absorbe']: {
        //echo Yaml::dump(['this'=> $this->asArray()]);
        $statuts = [];
        foreach ($this->evtsFin->absorbe as $cinseeAbsorbee) {
          $absorbee = Histo::$all[$cinseeAbsorbee]->versionParDateDeFin($this->dFin);
          $statuts[$absorbee->statut] = 1;
        }
        $next = $this->next();
        if (!$next) {
          $nextCode = $this->evtsFin->changeDeCodePour;
          $next = Histo::$all[$nextCode]->version($this->dFin);
        }
        if (isset($statuts['s'])) { // si au moins une des absorbées est une c.s. alors l'absorbante grossit
          Zone::includes($next->id(), $this->id());
          //echo "  Zone::includes(",$next->id(),", ",$this->id(),");\n";
        }
        else { // sinon l'absorbante est identique avant et après
          Zone::sameAs($next->id(), $this->id());
          //echo "  Zone::sameAs(",$next->id(),", ",$this->id(),");\n";
        }
        break;
      }
      
      // la rattachante grossit
      case ['prendPourAssociées']:
      case ['prendPourDéléguées']:
      case ['absorbe','prendPourAssociées']:
      case ['prendPourAssociées','absorbe']:
      case ['prendPourDéléguées','absorbe']:
      case ['prendPourAssociées','gardeCommeAssociées']:
      case ['prendPourDéléguées','gardeCommeDéléguées']:
      case ['gardeCommeDéléguées','prendPourDéléguées']:
      case ['prendPourDéléguées','absorbe','gardeCommeDéléguées']:
      case ['seDétacheDe','prendPourAssociées']:
      case ['estModifiéeIndirectementPar']: { // dans le cas de figure la suivante est plus grosse
        Zone::includes($this->next()->id(), $this->id());
        break;
      }
      
      // la rattachante grossit
      // et la nouvelle déléguée propre est identique à l'ancienne CS d'origine (l'ancienne CS peut déjà être rattachante)
      // correction 12/8/2020
      case ['devientDéléguéeDe','prendPourDéléguées']:
      case ['devientDéléguéeDe','prendPourDéléguées','absorbe']: 
      case ['devientDéléguéeDe','prendPourDéléguées','gardeCommeDéléguées']: 
      case ['devientDéléguéeDe','prendPourDéléguées','absorbe','gardeCommeDéléguées']: {
        Zone::includes($this->next()->id(), $this->id());
        Zone::sameAs($this->next()->rid(), $this->idNonRattachante());
        break;
      }
      
      
      case ['contribueA']: {
        Zone::includes($this->id(), $this->next()->id()); // la version suivante est incluse dans la version courante
        break;
      }
      
      case ['seScindePourCréer']:
      case ['détacheCommeSimples']:
      case ['détacheCommeSimples','seScindePourCréer']:
      case ['gardeCommeAssociées','détacheCommeSimples']:
      case ['détacheCommeSimples','gardeCommeAssociées']:
      case ['détacheCommeSimples','sAssocieA']:
      case ['détacheCommeSimples','devientDéléguéeDe']: {
        Zone::includes($this->id(), $this->next()->id()); // la version suivante est incluse dans la version courante
        if ($détacheCommeSimples = $this->evtsFin->détacheCommeSimples) {
          foreach ($détacheCommeSimples as $nvCinsee) // chaque c. créée est incluse dans la version courante
            Zone::includes($this->id(), Histo::$all[$nvCinsee]->version($this->dFin)->id());
        }
        if ($seScindePourCréer = $this->evtsFin->seScindePourCréer) {
          foreach ($seScindePourCréer as $nvCinsee) // chaque c. créée est incluse dans la version courante
            Zone::includes($this->id(), Histo::$all[$nvCinsee]->version($this->dFin)->id());
        }
        break;
      }
      
      case ['changeDeCodePour']: {
        $nvCinsee = $this->evtsFin->changeDeCodePour;
        Zone::sameAs($this->id(), Histo::$all[$nvCinsee]->version($this->dFin)->id());
        break;
      }
      
      default: {
        throw new Exception("$this->evtsFin , this=$this");
      }
    }
  }
};

class Evts {
  protected $evts; // array
  
  function __construct($evts) { $this->evts = $evts; }
  function keys(): array { return array_keys($this->evts); }
  function __get(string $key) { return $this->evts[$key] ?? null; }
  function asArray(): array { return $this->evts; }
  function __toString(): string { return json_encode($this->evts, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
}
