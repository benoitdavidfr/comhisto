<?php
/*PhpDoc:
name: histo.inc.php
title: histo.inc.php - classes Histo, Version et EltSet utilisées par fcomhisto.php
doc: |
journal: |
  8/11/2020:
    - passage en v2
  18/9/2020:
    - création
*/

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// Historique associé à un code Insee
class Histo {
  static $all=[]; // [cinsee => Histo]
  protected $cinsee; // code Insee
  protected $versions; // [date => Version]
  protected $vvalide; // ?Version - la version valide ou null si le code est périmé
  
  static function load(string $fpath) {
    $yaml = Yaml::parseFile($fpath);
    //print_r($yaml);
    foreach ($yaml['contents'] as $cinsee => $histo) {
      self::$all[$cinsee] = new Histo($cinsee, $histo);
    }
    //print_r(self::$all);
  }
    
  function __construct(string $cinsee, array $histo) {
    $this->cinsee = $cinsee;
    $vprec = null;
    foreach ($histo as $dv => $version) {
      if ($vprec)
        $vprec->setFin($dv, $version['évts'] ?? []);
      $this->versions[$dv] = new Version($cinsee, $dv, $version);
      $vprec = $this->versions[$dv];
    }
    
    $this->vvalide = array_values($this->versions)[count($this->versions)-1];
    if (in_array($cinsee, ['97123','97127'])) // Cas particuliers de St Barth et St Martin
      $this->vvalide = array_values($this->versions)[0];
    elseif (!$this->vvalide->etat()) // ne correspond pas réellement à une version mais à un évt de suppression
      $this->vvalide = null;
    elseif ($this->vvalide->fin()) // si la date de fin est définie alors le code est périmé/abrogé
      $this->vvalide = null;
  }
  
  static function get(string $cinsee): self {
    if (isset(self::$all[$cinsee]))
      return self::$all[$cinsee];
    else
      throw new Exception("aucun Histo ne correspond à $cinsee");
  }
  
  static function getVersion(string $id): Version {
    $type = substr($id, 0, 1);
    $cinsee = substr($id, 1, 5);
    $dv0 = substr($id, 7);
    if (!isset(self::$all[$cinsee]))
      throw new Exception("aucun Histo ne correspond à $id");
    $histo = self::$all[$cinsee];
    if (isset($histo->versions[$dv0])) {
      return $histo->versions[$dv0];
    }
    else {
      foreach ($histo->versions as $dv => $version) {
        if (($dv <= $dv0) && (!$version->fin() || ($dv0 < $version->fin())))
          return $version;
      }
    }
    throw new Exception("aucune Version ne correspond à $id");
  }
  
  function asArray(): array {
    $array = [];
    foreach ($this->versions as $dv => $version)
      $array[$dv] = $version->asArray();
    return $array;
  }

  static function allAsArray(): array {
    $array = [];
    foreach (self::$all as $cinsee => $histo) {
      $array[$cinsee] = $histo->asArray();
    }
    return $array;
  }

  function vvalide(): ?Version { // retourne la version valide si elle existe, null sinon
    return $this->vvalide;
  }

  // retourne la liste des noms
  function names(): array {
    $names = []; // [ nom => 1 ]
    foreach ($this->versions as $dv => $version) {
      if ($name = $version->etat()['name'] ?? null)
        $names[$name] = 1;
    }
    return array_keys($names);
  }
  
  // retourne les coord. [lon,lat] du chef-lieu
  function chefLieu(): array {
    $cinsee = $this->cinsee;
    $sql = "select ST_AsGeoJSON(wkb_geometry) from chef_lieu_carto where insee_com='$cinsee'";
    //echo "$sql\n";
    $tuples = PgSql::getTuples($sql);
    if (count($tuples) > 0) {
      $geojson = json_decode($tuples[0]['st_asgeojson'], true);
      return $geojson['coordinates'];
    }
    foreach ($this->names() as $name) {
      try {
        return ChefLieu::chercheGeo($this->cinsee, $name);
      }
      catch (Exception $e) {}
    }
    if ($cinsee = $this->changeDeCodePour()) {
      return Histo::get($cinsee)->chefLieu();
    }
    throw new Exception("coord. non trouvées pour $this->cinsee, ".implode(',', array_keys($this->names())));
  }
  
  function changeDeCodePour(): ?string { // retourne le nouveau code ou null s'il n'y a pas de chgt de code
    $derniereVersion = array_values($this->versions)[count($this->versions)-1];
    return $derniereVersion->evts()['changeDeCodePour'] ?? null;
  }
  
  function insertComhisto(): void { // insertion des versions dans la table comhisto
    foreach ($this->versions as $version)
      $version->insertComhisto();
  }
};

// Version d'un Historique
class Version {
  protected $cinsee;
  protected $debut;
  protected $fin;
  protected $evts;
  protected $evtsFin; // array
  protected $etat;
  protected $erat; // [{codeInsee}]
  protected $eltSet; // ?EltSet - elits positifs et propres, cad hors ERAT
  protected $eltSetND; // ?EltSet - dans le cas de 33055, elits non délégués
  
  function __construct(string $cinsee, string $debut, array $version) {
    $this->cinsee = $cinsee;
    $this->debut = $debut;
    $this->evts = $version['évts'] ?? [];
    $this->fin = null;
    $this->evtsFin = [];
    $this->etat = $version['état'] ?? [];
    $this->erat = $version['erat'] ?? [];
    $this->eltSet = isset($version['élits']) ? new EltSet($version['élits']) : null;
    $this->eltSetND = isset($version['élitsNonDélégués']) ? new EltSet($version['élitsNonDélégués']) : null;
    //print_r($version);
  }
  
  function setFin(string $fin, $evtsFin) { $this->fin = $fin; $this->evtsFin = $evtsFin; }
  function type(): string { return in_array($this->etat['statut'], ['COMA', 'COMD', 'ARM']) ? 'r' : 's'; }
  function cinsee(): string { return $this->cinsee; }
  function statut(): string { return $this->etat['statut'] ?? 'undef'; }
  function etat(): array { return $this->etat; }
  
  function erats(): array { // [ Version ]
    $erats = [];
    foreach ($this->erat as $codeInsee) {
      $erats[] = Histo::getVersion("r$codeInsee@$this->debut");
    }
    return $erats;
  }
  
  function evts(): array { return $this->evts; }
  function eltSet(): ?EltSet { return $this->eltSet; }
  function eltSetND(): ?EltSet { return $this->eltSetND; }
  function debut(): string { return $this->debut; }
  function fin(): ?string { return $this->fin; }
  
  function name(string $type): ?string {
    if (!$this->etat)
      return '';
    elseif (isset($this->etat['nomCommeDéléguée']) && ($type=='r'))
      return $this->etat['nomCommeDéléguée'];
    else
      return $this->etat['name'];
  }
  
  function asArray(): array {
    return array_merge(
      ['debut'=> $this->debut],
      $this->evts ? ['évts'=> $this->evts] : [],
      $this->fin ? ['fin'=> $this->fin] : [],
      $this->evtsFin ? ['évtsFin'=> $this->evtsFin] : [],
      $this->etat ? ['état'=> $this->etat] : [],
      $this->erat ? ['erat'=> $this->erat] : [],
      $this->eltSet ? ['eltsp'=> $this->eltSet->__toString()] : [],
      $this->eltSetND ? ['eltsNonDélégués'=> $this->eltSetND->__toString()] : []
    );
  }
  
  function estAssociation(): bool { return ($erat0 = $this->erats()[0] ?? null) ? ($erat0->statut()=='COMA') : false; }
  function estCNouvelle():  bool  { return ($erat0 = $this->erats()[0] ?? null) ? in_array($erat0->statut(), ['COMD','COM']) : false; }
  function estCAvecARM():   bool  { return ($erat0 = $this->erats()[0] ?? null) ? ($erat0->statut()=='ARM') : false; }
  
  function existeDelegueePropre(): bool { // teste si la version correspond à une commune mixte cad nouvelle avec déléguée propre
    return in_array($this->cinsee, $this->erat);
  }
  
  function elitsAvecErat(): array { // construit l'ensemble des elits associés à la version et à ses erat éventuels
    $eltsAvecErat = $this->eltSet ? $this->eltSet->elts() : [];
    foreach ($this->erats() as $erat) {
      foreach ($erat->eltSet->elts() as $elt)
        $eltsAvecErat[] = $elt;
    }
    return $eltsAvecErat;
  }
  
  function insertComhisto(bool $recursif=false): void {
    //echo "appel de insertComhisto() sur ",$this->statut()," ",$this->cinsee,"\n";
    if (!$this->etat) return;
    // Dans le cas d'une commune mixte, génération d'une part de la crat et d'autre part de la déléguée propre
    if (!$recursif && $this->existeDelegueePropre()) {
      //echo "Appels récursifs\n";
      $this->comNouvelle()->insertComhisto(true);
      $this->delegueePropre()->insertComhisto(true);
      return;
    }
    $elts = $this->elitsAvecErat();
    if (count($elts) == 1) {
      $elt = $elts[0];
      $geomsql = "geom from elit where cinsee='$elt'";
    }
    else {
      $geomsql = "ST_Union(geom) from elit where cinsee in ('".implode("','", $elts)."')";
    }
    $type = $this->type();
    $cinsee = $this->cinsee;
    $ddebut = $this->debut;
    $edebut = $this->evts ? $this->evts : ['entreDansLeRéférentiel'=> null];
    $dfin = $this->fin ? "'".$this->fin."'" : 'null';
    if ($efin = $this->evtsFin) {
      $efin = json_encode($efin, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
      $efin = "'".str_replace("'", "''", $efin)."'";
    }
    else {
      $efin = 'null';
    }
    $statut = $this->statut();
    if ($statut == 'COM') {
      if ($this->estAssociation())
        $statut = 'ASSO';
      elseif ($this->estCNouvelle())
        $statut = 'NOUV';
      else
        $statut = 'BASE';
    }
    $edebut = json_encode($edebut, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $edebut = str_replace("'", "''", $edebut);
    $crat = isset($this->etat['crat']) ? "'".$this->etat['crat']."'" : 'null';
    $erats = $this->erat ? array_values($this->erat)[0] : [];
    $erats = json_encode($erats);
    if ($this->eltSet) {
      $elits = json_encode($this->eltSet->elts());
      $elits = "'".str_replace("'", "''", $elits)."'";
    }
    else
      $elits = 'null';
    $dnom = str_replace("'", "''", $this->etat['name']);
    $sql = "insert into comhistog3(id, type, cinsee, ddebut, edebut, dfin, efin, statut, crat, erats, elits, dnom, geom)\n"
      ."select '$type$cinsee@$ddebut', '$type', '$cinsee', '$ddebut', '$edebut', $dfin, $efin,"
      ." '$statut', $crat, '$erats', $elits, '$dnom', $geomsql";
    //echo "sql=$sql\n";
    try {
      if (($affrows = PgSql::query($sql)->affected_rows()) <> 1) {
        echo "Erreur sur affected_rows=$affrows, sql=$sql\n";
        //die("Erreur affected_rows\n");
      }
    }
    catch (Exception $e) {
      echo $e->getMessage(),"\n";
      echo "sql=$sql\n";
      die("Erreur Sql\n");
    }
  }
  
  // Dans le cas d'une commune mixte renvoie un objet simulant la commune déléguée
  function delegueePropre(): self {
    $delPropre = clone $this;
    unset($delPropre->evts['prendPourDéléguées']);
    $delPropre->etat = [
      'name'=> $delPropre->etat['nomCommeDéléguée'] ?? $delPropre->etat['name'],
      'statut'=> 'COMD',
      'crat'=> $this->cinsee,
    ];
    $delPropre->erat = [];
    return $delPropre;
  }
  
  // Dans le cas d'une commune mixte renvoie un objet simulant la commune de rattachement
  function comNouvelle(): self {
    $comNouv = clone $this;
    unset($comNouv->evts['devientDéléguéeDe']);
    $comNouv->etat = [
      'name'=> $comNouv->etat['name'],
      'statut'=> 'NOUV',
    ];
    $comNouv->eltSet = $comNouv->eltSetND;
    $comNouv->eltSetND = null;
    return $comNouv;
  }

  // construit la liste des couples CEntElts associés à une version valide
  function cEntElits(): array { // [CEntElits]
    /* Algo:
    - je construis les objets CEntElts = couple (entité (coms, erat, ecomp) définie dans COG2020, éléments correspondants)
      - si vvalide est un COMS sans ERAT alors (coms, elts)
      - si vvalide est un ERAT  alors (erat, elts)
      - si vvalide est un COMS avec ERAT alors il y a potentiellement 2 entités
        - celle correspondant à une éventuelle commune déléguée propre (ex. r01015)
        - celle correspondant à une éventuelle ECOMP avec 3 cas d'ECOMP:
          - dans le cas d'une association, le territoire de la commune chef-lieu est une ECOMP (ex c38139)
          - dans le cas d'une C. nouv. sans déléguée propre, le territoire de la C chef-lieu est une ECOMP (ex 11171 / 11080 -> 11080c)
          - dans le cas de la commune nouvelle 33055, la commune d'origine 33338 est absorbées dans la c. nouv. (ex 33338/33055)
    */
    $cinsee = $this->cinsee;
    if (in_array($this->statut(), ['COMD','COMA','ARM'])) { // ERAT
      return [new CEntElits("r$cinsee", $this->eltSet())];
    }
    elseif (!($erats = $this->erats())) { // COMS sans ERAT
      return [new CEntElits("s$cinsee", $this->eltSet())];
    }
    // COM avec ERAT
    elseif ($this->estCAvecARM()) { // dans les cas de C. avec ARM aucun couple associé
      return [];
    }
    elseif ($this->estAssociation()) { // dans le cas d'une association, le territoire de la commune chef-lieu est une ECOMP
      return [new CEntElits("c$cinsee", $this->eltSet())];
    }
    elseif ($this->eltSetND()) { // dans le cas de la C nouvelle 33055, la c d'origine 33338 est absorbées dans la c. nouv.
      return [
        new CEntElits("c$cinsee", $this->eltSetND()),
        new CEntElits("r$cinsee", $this->eltSet()),
      ];
    }
    elseif ($this->estCNouvelle()) { // dans les autres cas de C. nouv., déléguée propre ou non
      if ($this->existeDelegueePropre()) // s'il existe une delegue propre
        return [new CEntElits("r$cinsee", $this->eltSet())];
      else // sinon
        return [new CEntElits("c$cinsee", $this->eltSet())];
    }
    else {
      echo "Cas non traité dans Version::cEntElits() pour $cinsee\n";
      print_r($this); die();
      return [];
    }
  }
};

class EltSet { // Ensemble d'éléments
  protected $set; // [eelt => 1]
  
  function __construct(array $elts) { // création à partir d'une liste de chaines de codes Insee
    $this->set = [];
    foreach ($elts as $elt)
      $this->set["e$elt"] = 1;
    ksort($this->set);
  }
  
  function __toString(): string { return implode('+', array_keys($this->set)); }
  
  //function empty(): bool { return ($this->set==[]); }
  
  // nbre d'éléments dans l'ensemble
  function count(): int { return count($this->set); }
  
  function elts(): array {
    $elts = [];
    foreach (array_keys($this->set) as $eelt)
      $elts[] = substr($eelt, 1);
    return $elts;
  }
};

