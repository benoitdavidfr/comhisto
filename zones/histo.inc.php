<?php
/*PhpDoc:
name: histo.inc.php
title: histo.inc.php - def. des classes Histo, Version et Evt pour gérer le fichier Histo
screens:
doc: |
  Charge le fichier Histo version Insee
  Traduit les relations entre versions en relations topologiques entre zones géographiques:
    - sameAs pour identité des zones géographiques entre 2 versions
    - includes(a,b) pour inclusion de b dans a
  Ces relations topologiques permettront dans la classe Zone de construire les zones géographiques
  et les relations d'inclusion entre elles.
journal: |
  21/7/2020:
    - reprise à partir de ../../rpicom/rpigeo/bzone.php
*/

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Histo {
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
  
  function version(string $dCreation): ?Version { return $this->versions[$dCreation] ?? null; }

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
      if (!isset(self::$all[$cinsee]->versions[$dCreation]))
        throw new Exception("Version $dCreation du Rpicom $cinsee n'existe pas");
      //echo "get($id)->"; print_r(self::$all[$cinsee]->versions[$dCreation]);
      return self::$all[$cinsee]->versions[$dCreation];
    }
  }
  
  // Fabrique ttes les zones
  static function buildAllZones(): void {
    foreach (self::$all as $cinsee => $histo)
      $histo->buildZones();
    Zone::traiteInclusions();
  }
  
  // Fabrique les zones corr. à un Histo
  function buildZones(): void {
    //echo Yaml::dump([$this->cinsee => $this->asArray()], 3, 2);
    /* gère dans un premier temps le cas illustré par 27111 de fusion suivie d'un rétablissement
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
    //return;
    
    foreach ($this->versions as $version) {
      $version->buildZones();
    }
  }
};

class Version {
  protected $cinsee; // code insee
  protected $dCreation; // date de création
  protected $dFin; // date de fin ssi périmée sinon null
  protected $statut; // statut simplifié - 's' pour simple, 'r' pour rattachée
  protected $crat; // null ssi s sinon code insee de la commune de rattachement
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
  function isValid(): bool { return is_null($this->dFin); }

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

    switch ($this->evtsFin->keys()) {
      // Il n'y a pas d'entité suivante
      case ['sortDuPérimètreDuRéférentiel']: break;
      
      // l'entité suivante est identique à l'entité courante
      case ['changeDeNomPour']:
      case ['devientDéléguéeDe']:
      case ['sAssocieA']:
      case ['resteAssociéeA']:
      case ['resteDéléguéeDe']:
      case ['changedAssociéeEnDéléguéeDe']:
      case ['gardeCommeDéléguées']: // cas de 22183@2016-01-01,
      case ['seSépareDe']:
      case ['seSépareDe','sAssocieA']:
      case ['créationDUneRattachéeParScissionDe']:
      case ['changeDeRattachementPour']: {
        Zone::sameAs($this->id(), $this->next()->id());
        break;
      }
      
      // l'entité courante est incluse dans l'entité absorbante
      case ['seFondDans']:
      case ['fusionneDans']: {
        if ($this->statut == 's') {
          $crat = $this->evtsFin->asArray()[$this->evtsFin->keys()[0]];
          Zone::includes("s$crat@$this->dFin", $this->id());
        }
        break;
      }
      
      // A améliorer, il faudrait définir les différents morceaux transférés dans différentes communes
      case ['seDissoutDans']: break; // il n'y a plus rien après
      
      case ['reçoitUnePartieDe']:
      case ['changeDeNomPour','reçoitUnePartieDe']: {
        Zone::includes($this->next()->id(), $this->id()); // la version suivante inclus la version courante
        break;
      }
      
      case ['absorbe']:
      case ['absorbe','gardeCommeAssociées']:
      case ['gardeCommeAssociées','absorbe']: {
        //echo Yaml::dump(['this'=> $this->asArray()]);
        $statuts = [];
        foreach ($this->evtsFin->__get('absorbe') as $cinseeAbsorbee) {
          $absorbee = Histo::$all[$cinseeAbsorbee]->versionParDateDeFin($this->dFin);
          $statuts[$absorbee->statut] = 1;
        }
        if (isset($statuts['s'])) { // si au moins une des absorbée est une c.s. alors l'absorbante grossit
          Zone::includes($this->next()->id(), $this->id());
          //echo "  Zone::includes(",$this->next()->id(),", ",$this->id(),");\n";
        }
        else { // sinon l'absorbante est identique avant et après
          Zone::sameAs($this->next()->id(), $this->id());
          //echo "  Zone::sameAs(",$this->next()->id(),", ",$this->id(),");\n";
        }
        break;
      }
      
      case ['prendPourAssociées']:
      case ['prendPourDéléguées']:
      case ['absorbe','prendPourAssociées']:
      case ['prendPourAssociées','absorbe']:
      case ['prendPourDéléguées','absorbe']:
      case ['prendPourDéléguées','gardeCommeDéléguées']:
      case ['gardeCommeDéléguées','prendPourDéléguées']:
      case ['prendPourDéléguées','absorbe','gardeCommeDéléguées']:
      case ['seSépareDe','prendPourAssociées']: { // la rattachante grossit
        Zone::includes($this->next()->id(), $this->id());
        break;
      }
      
      case ['délègueA']: {
        $deleguees = $this->evtFin->asArray()[$this->evtsFin->keys()[0]];
        if ($deleguees == [$this->cinsee])
          // cas très particulier où la seule déléguée est elle-même, ce qui ne doit jamais exister
          Zone::sameAs($this->next()->id(), $this->id());
        else {
          // la commune rattachante s'agrandit
          Zone::includes($this->next()->id(), $this->id());
          if (in_array($this->cinsee, $deleguees)) { // si auto-déléguée
            // la version actuelle est égale à l'auto-déléguée créée
            Zone::sameAs($this->id(), 'r'.$this->cinsee.'@'.$this->dFin);
            //echo "Zone::sameAs(d",$this->cinsee.'@'.$this->dFin,', ', $this->id(),");\n";
          }
        }
        break;
      }
      
      case ['contribueA']:
      case ['détacheCommeSimples']:
      case ['gardeCommeAssociées','détacheCommeSimples']:
      case ['détacheCommeSimples','gardeCommeAssociées']: 
      case ['détacheCommeSimples','seScindePourCréerLesAssociées']: {
        Zone::includes($this->id(), $this->next()->id()); // la version suivante est incluse dans la version courante
        break;
      }

      case ['seScindePourCréer']: {
        Zone::includes($this->id(), $this->next()->id()); // la version suivante est incluse dans la version courante
        foreach ($this->evtsFin->asArray()['seScindePourCréer'] as $nvCinsee)
          Zone::includes($this->id(), "s$nvCinsee@".$this->dFin); // chaque c. créée est incluse dans la version courante
        break;
      }
      
      case ['seScindePourCréerLesNouveauxArrondissementsMunicipaux']: {
        Zone::includes($this->id(), $this->next()->id()); // la version suivante est incluse dans la version courante
        foreach ($this->evtsFin->asArray()['seScindePourCréerLesNouveauxArrondissementsMunicipaux'] as $nvCinsee)
          Zone::includes($this->id(), "r$nvCinsee@".$this->dFin); // chaque e. créée est incluse dans la version courante
        break;
      }
      
      case ['prendLeRattachementDe']: {
        break;
      }
      
      case ['perdRattachementPour']: {
        // la zone de la c. actuelle est identique à celle de la future rattachante
        $nlleRat = $this->evtsFin->asArray()['perdRattachementPour'];
        Zone::sameAs($this->id(), "s$nlleRat@".$this->dFin);
        // la future zone de la c. actuelle est identique à la zone de la commune au 1/1/1943
        // Cela permet de donner cette relation avec la version avant associations
        Zone::sameAs('r'.$this->cinsee.'@'.$this->dFin, 's'.$this->cinsee.'@1943-01-01');
        break;
      }
      
      // {"absorbe":[14507],"quitteLeDépartementEtPrendLeCode":50649}
      case ['absorbe', 'quitteLeDépartementEtPrendLeCode'] :
      case ['quitteLeDépartementEtPrendLeCode']: {
        $nvCinsee = $this->evtsFin->__get('quitteLeDépartementEtPrendLeCode');
        Zone::sameAs($this->id(), Histo::$all[$nvCinsee]->version($this->dFin)->id());
        //echo "Zone::sameAs(",$this->id(),", ",Rpicom::idByCinseeAndDate($nvCinsee, $this->dFin),");\n";
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
