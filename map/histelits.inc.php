<?php
/*PhpDoc:
name: histelits.inc.php
title: map/histelits.inc.php - lecture du fichier histelits, stockage des histelits et calcul du cluster
doc: |
  Le fichier pser est stocké comme un objet AutoDescribed pour garder une compatibilité avec YamlDoc.
  La classe Histelits stocke les histelits et implémente qqs méthodes

  Le cluster associé à un code Insee est un extrait des histelits qui correspond aux codes Insee associés par des relations.
journal: |
  12/11/2020:
    - création
*/
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class AutoDescribed { // Pour garder une compatibilité avec YamlDoc, le pser est enregistré comme objet AutoDescribed
  protected $_id;
  protected $_c;

  function __construct(array $c, string $docid) { $this->_c = $c; $this->_id = $docid; }
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; } // lit les champs
  function asArray() { return $this->_c; }
};

class Histelits extends AutoDescribed {
  static $all = []; // stockage des histelits [cinsee => histelit]

  static function readfile(string $path): void { // lit un fichier si possible en pser sinon en Yaml
    if (is_file("$path.pser") && (filemtime("$path.pser") > filemtime("$path.yaml"))) {
      $file = unserialize(file_get_contents("$path.pser"));
      self::$all = $file->contents;
    }
    else {
      $yaml = Yaml::parseFile("$path.yaml");
      file_put_contents("$path.pser", serialize(new self($yaml, '')));
      self::$all = $yaml['contents'];
    }
  }
  
  static function cluster(string $path, string $code0): array { // retourne un extrait de $all avec les enregistrements en cluster
    self::readfile($path);
    if (!isset(self::$all[$code0]))
      return [];
    $cluster = [$code0 => 1];
    foreach (self::$all[$code0] as $version) {
      if ($c = $version['évts']['avaitPourCode'] ?? null)
        $cluster[$c] = 1;
      if ($c = $version['évts']['changeDeCodePour'] ?? null)
        $cluster[$c] = 1;
      foreach ($version['évtsSrc']['crééeAPartirDe'] ?? [] as $srce)
        $cluster[$srce] = 1;
      if ($c = $version['évtsSrc']['contribueA'] ?? null)
        $cluster[$c] = 1;
      if ($c = $version['évts']['fusionneDans'] ?? null)
        $cluster[$c] = 1;
      foreach ($version['évts']['absorbe'] ?? [] as $absorbee)
        $cluster[$absorbee] = 1;
      if ($c = $version['état']['crat'] ?? null)
        $cluster[$c] = 1;
      foreach ($version['erat'] ?? [] as $erat)
        $cluster[$erat] = 1;
    }
    foreach (array_keys($cluster) as $c)
      $cluster[$c] = self::$all[$c];
    return $cluster;
  }
  
  // calcule les elits étendus, cad elits propres + elits des erats, triés ; $vid est l'identifiant de version
  // le statut est indispensable pour distinguer une déléguée propre de sa crat
  static function elitEtendus(string $vid, string $statut): string {
    $type = substr($vid, 0, 1); // 'r' ou 's'
    $cinsee = substr($vid, 1, 5); // code Insee
    $ddebut = substr($vid, 7); // date de début
    //echo "type=$type, cinse=$cinsee, ddebut=$ddebut\n";
    $elitEtendus = [];
    foreach (self::$all[$cinsee][$ddebut]['élits'] as $elit)
      $elitEtendus[$elit] = 1;
    if (in_array($statut, ['ASSO','NOUV','CARM'])) {
      foreach (self::$all[$cinsee][$ddebut]['erat'] ?? [] as $erat) {
        foreach (self::$all[$erat][$ddebut]['élits'] ?? [] as $elit) // petit bug, je n'ai pas forcément l'erat à cette date, ex 69123
          $elitEtendus[$elit] = 1;
      }
      foreach (self::$all[$cinsee][$ddebut]['élitsNonDélégués'] ?? [] as $elit)
        $elitEtendus[$elit] = 1;
    }
    ksort($elitEtendus);
    return implode(',', array_keys($elitEtendus));
  }
};
