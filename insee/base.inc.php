<?php
/*PhpDoc:
name: base.inc.php
title: base.inc.php - Gestion d'une base en mémoire + Gestion de critères utile pour gérer la trace
doc: |
  La classe Base gère une base d'objets en mémoire enregistrée en pser de manière compatble avec YamlDoc.
  La classe Criteria gère des critères utilisés pour afficher une trace dans la classe Base.
journal: |
  18/4/2020:
    - chgt du format du pser de Base pour assurer la compatibilité avec YamlDoc
  16/4/2020:
    - amélioration de la doc
  11/4/2020:
    - créé par extraction de index.php
classes:
*/
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

{/*PhpDoc: classes
name: Criteria
title: class Criteria - Enregistre des critères et les teste
methods:
doc: |
  - la méthode __construct() initialise les critères définis sous la forme:
      [({var} => [{val}] | {var} => ['not'=>[{val}]] | 'not')]
    qui s'interprètent par:
      - si [] alors est toujours vrai
      - si [ 'not' ] alors est toujours faux
      - si [ {var} => [{val}] ] alors la valeur d'une variable {var} doit être parmi les valeurs [{val}]
      - si [ {var} => ['not'=> [{val}]] ] alors, à l'inverse, la valeur de {var} ne doit pas être parmi les valeurs [{val}]
  - la méthode is() évalue si les critères sont vérifiés pour un ensemble de variables prenant chacune une valeur défini
    dans le paramètre $params.
    Le résultat est vrai ssi pour chaque variable {var} à la fois dans $params et dans $criteria le critère correspondant 
    est respecté, c'est à dire:
      - s'il est de la forme [{val}] et que la valeur de la variable {var} appartient à cet ensemble
      - sinon s'il est de la forme ['not'=>[{val}]] et que la valeur de la variable {var} n'appartient pas à cet ensemble
    Si $criteria vaut [] alors is() retourne vrai
    Si $criteria vaut ['not'] alors is() retourne faux
  - la méthode statique test() teste la classe
*/}
class Criteria {
  protected $criteria; // critères sous la forme [({var} => [{val}] | {var} => ['not'=>[{val}]] | 'not')]
  
  function __construct(array $criteria) { $this->criteria = $criteria; }
  
  function is(array $params): bool {
    {/*PhpDoc: methods
    name: is
    title: "function is(array $params): bool - évalue si les critères sont vérifiés pour un ensemble de variables prenant chacune une valeur"
    */}
    if ($this->criteria == ['not'])
      return false;
    foreach ($this->criteria as $var => $criterium) {
      if (isset($criterium['not'])) {
        if (isset($params[$var]) && in_array($params[$var], $criterium['not']))
          return false;
      }
      else {
        if (isset($params[$var]) && !in_array($params[$var], $criterium))
          return false;
      }
    }
    return true;
  }
  
  static function test() { // Test de la classe
    {/*PhpDoc: methods
    name: is
    title: "static function test() - Test de la classe"
    */}
    if (0) {
      $trace = new self(['var'=>['Oui']]);
      //$trace = new self(['var'=>['not'=> ['Oui']]]);
      foreach([
        ['var'=>'Oui'],
        ['var'=>'Non'],
        ['var2'=>'xxx']] as $params)
          echo Yaml::dump($params),"-> ",$trace->is($params) ? 'vrai' : 'faux', "<br>\n";
    }
    if (1) {
      //$trace = new self(['mod'=> ['not'=> ['31']]]); // affichage mod <> 31
      $trace = new self(['mod'=> ['31']]); // affichage mod == 31
      foreach([
        ['mod'=>'31'],
        ['mod'=>'XXX'],
        ['var2'=>'xxx']] as $params)
          echo Yaml::dump($params),"-> ",$trace->is($params) ? 'vrai' : 'faux', "<br>\n";
    }
    die("Criteria::test()");
  }
};

// Pour garder une compatibilité avec YamlDoc, le pser est enregistré comme objet AutoDescribed
class AutoDescribed {
  protected $_id;
  protected $_c;

  function __construct(array $c, string $docid) { $this->_c = $c; $this->_id = $docid; }
  function asArray() { return $this->_c; }
};

{/*PhpDoc: classes
name: Base
title: class Base - Gestion d'une base en mémoire
doc: |
  La méthode __construct() initialise la base en mémoire à partir du champ contents d'un fichier Yaml ou pser.
  Les méthodes __set(), __get(), __isset() et __unset() modifie ou utilise la base
    - $base->$key = $record pour mettre à jour l'enregistrement correspondant à la clé $key
    - $base->$key pour consulter l'enregistrement correspondant à la clé $key
    - isset($base->$key) pour tester si la clé $key existe dans la base
    - unset($base->$key) pour effacer l'enregistrement correspondant à la clé $key
  La méthode __toString() retourne la base, y compris ses métadonnées, encodée en JSON
  La méthode save() enregistre la base modifiée dans un fichier pser
  La méthode writeAsYaml() enregistre la base dans un fichier Yaml
  Les méthodes de gestion de la base affichent un message en fonction des critères trace définis par le paramètre $trace
  lors de la création et les variables de trace définies par setTraceVar()
  
  Pour que le pser soit compatible avec YamlDoc, il est stocké comme objet AutoDescribed, définie de manière simplifiée
methods:
*/}
class Base {
  protected $filepath; // string - chemin initial du fichier Yaml/pser, évt '' qui signifie que la base a été init. à vide
  protected $base; // [ {key} => {record} ] - contenu des enregistrements de la base
  protected $metadata; // [ {key} => {val} ] - liste des métadata, si possible DublinCore
  protected $trace; // Criteria - critères de trace
  protected $traceVars = []; // [string] - variables utilisées pour tester si la trace est active ou non
  protected $extractAsYaml; // [string => 1] ou null - utilisé par startExtractAsYaml() / showExtractAsYaml()
  
  // affectation d'une des variables utilisées pour tester la verbosité
  function setTraceVar(string $var, $val) { $this->traceVars[$var] = $val; }
  
  function __construct($data='', Criteria $trace=null) {
    {/*PhpDoc: methods
    name: __construct
    title: function __construct($data='', Criteria $trace=null) - Initialisation de la base
    doc: |
      La base est initialisée à partir soit d'un Yaml, soit d'un fichier, soit de rien.
      Si $data est un string alors
        Si $data=='' alors la base est initialisée à vide ;
        SinonSi le fichier $data.pser existe et est plus récent qu'un éventuel fichier yaml
          alors ce fichier pser est utilisé ;
        Sinonsi le fichier yaml existe
          alors il est utilisé et enregistré en pser (pour accélérer une prochaine utilisation) ;
        Sinon la base est initialisée à vide.
      SinonSi $data est un array alors la base est initailisée à partir de cet array
      Sinon exception
      
      Si $data est un string <> '' mais que les fichiers n'existent pas alors ce $data sera utilisé lors d'un save().
      La base correspond au champ 'contents' du fichier Yaml/pser ; les autres champs sont considérés comme les métadonnées
      de la base.
    */}
    $this->filepath = ''; // initialisation de $this->filepath pour le cas où $data est un array
    if (is_string($data)) { // si string alors le chemin du fichier contenant les données
      //echo "création de Base(data='$data', trace)<br>\n";
      $fpath = $data;
      $this->filepath = $fpath;
      if (!$fpath) {
        $data = ['contents'=> []];
      }
      elseif (is_file("$fpath.pser") && (is_file("$fpath.yaml") && (filemtime("$fpath.pser") > filemtime("$fpath.yaml")))) {
        //echo "Lecture du pser<br>\n";
        $data = unserialize(file_get_contents("$fpath.pser"));
        //print_r($data);
        if (is_object($data)) { // le pser est enregistré comme objet AutoDescribed pour compatibilité avec YamlDoc
          //echo "data est un objet<br>\n";
          $data = $data->asArray();
        }
      }
      elseif (is_file("$fpath.yaml")) {
        $data = Yaml::parse(file_get_contents("$fpath.yaml"));
        $docid = 'rpicom/'.basename($fpath);
        file_put_contents("$fpath.pser", serialize(new AutoDescribed($data, $docid)));
      }
      else {
        $data = ['contents'=> []];
      }
    }
    elseif (!is_array($data))
      throw new Exception("Dans Base::__construct() le paramètre data doit avoir comme type soit string soit array");
    elseif (!array_key_exists('contents', $data))
      throw new Exception("Dans Base::__construct() le paramètre data s'il est array doit contenir un champ contents");
    $this->base = $data['contents'];
    unset($data['contents']);
    $this->metadata = $data;
    $this->trace = $trace ?? new Criteria([]);
    $this->extractAsYaml = null; // == null signifie que extract n'est pas en cours de constitution
  }
  
  function __set(string $key, $record): void {
    if ($this->trace->is($this->traceVars))
      echo "Base::__set($key, ",json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),")\n";
    $this->base[$key] = $record;
    if ($this->extractAsYaml !== null)
      $this->extractAsYaml[$key] = 1;
  }
  
  function __get(string $key) {
    if ($this->trace->is($this->traceVars))
      echo "Base::__get($key)\n";
    return $this->base[$key] ?? null;
  }
  
  function __isset(string $key): bool {
    if ($this->trace->is($this->traceVars))
      echo "Base::__isset($key)\n";
    return isset($this->base[$key]);
  }
  
  function __unset(string $key): void {
    if ($this->trace->is($this->traceVars))
      echo "Base::__unset($key)\n";
    unset($this->base[$key]);
  }
  
  // ajoute à l'enregistrement $key l'array $merge
  function mergeToRecord(string $key, array $merge): void {
    if (!isset($this->$key)) {
      $this->$key = $merge;
    }
    else {
      $record = $this->$key;
      //echo "key=$key, record="; print_r($record); echo 'merge='; print_r($merge);
      foreach ($merge as $key2 => $val) {
        if (isset($record[$key2])) {
          echo "création d'un bis sur $key/$key2\n";
          $key2 .= '-bis';
          if (isset($record[$key2]))
            throw new Exception("Carambolage sur $key/$key2");
        }
        $record[$key2] = $val;
      }
      $this->$key = $record;
    }
  }

  function __toString(): string {
    return json_encode(
      array_merge($this->metadata, ['contents'=> $this->base]),
      JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }
  
  // retourne les métadonnées comme array
  function metadata(): array { return $this->metadata; }
  
  // stocke des MD
  function storeMetadata(array $metadata): void { $this->metadata = $metadata; }
  
  // retourne le contenu comme array
  function contents(): array { return $this->base; }
  
  // tri les enregistrements sur leur clé
  function ksort(): void { ksort($this->base); }
  
  function save(string $filepath='', array $metadata=[]) {
    {/*PhpDoc: methods
    name: save
    title: function save(string $filepath='', array $metadata=[]) - sauve la base en PSER pour une réutilisation 
    doc: |
      Si $metadata non défini alors ceux initiaux sont repris
      Si $filepath est non défini alors
        s'il avait été défini à l'init. alors il est réutilisé
        sinon une Exception est levée
    */}
    if (!$filepath) {
      if (!$this->filepath)
        throw new Exception("Dans Base::save() le paramètre filepath doit être défini s'il ne l'a pas été à l'initialisation");
      $filepath = $this->filepath;
    }
    if (!$metadata)
      $metadata = $this->metadata;
    return file_put_contents("$filepath.pser", serialize(array_merge($metadata, ['contents'=> $this->base])));
  }

  function writeAsYaml(string $filepath='', array $metadata=[]) {
    {/*PhpDoc: methods
    name: writeAsYaml
    title: function writeAsYaml(string $filepath='', array $metadata=[]) - enregistre le contenu de la base dans un fichier Yaml
    doc: |
      Si $metadata non défini alors ceux initiaux sont repris
      Si $filepath est non défini alors
        s'il avait été défini à l'init. alors il est réutilisé
        sinon affichage du Yaml
    */}
    // post-traitement, suppression des communes ayant uniq. un nom comme propriété pour faciliter la visualisation
    if (0) {
      ksort($this->base);
      foreach ($this->base as $c => $com) {
        if (isset($com['name']) && (count(array_keys($com))==1))
          unset($this->base[$c]);
      }
    }
    if (!$metadata)
      $metadata = $this->metadata;
    if (!$filepath)
      $filepath = $this->filepath;
    else
      $this->filepath = $filepath;
    if ($filepath)
      return file_put_contents("$filepath.yaml", Yaml::dump(array_merge($metadata, ['contents'=> $this->base]), 99, 2));
    else
      echo Yaml::dump(array_merge($metadata, ['contents'=> $this->base]), 99, 2);
  }

  // démarre la constitution avec les enr. modifiés d'un extrait qui sera terminé et affiché par showExtractAsYaml()
  function startExtractAsYaml(): void { $this->extractAsYaml = []; }
  
  // affiche l'extrait démarré par startExtractAsYaml() et termine l'extrait
  function showExtractAsYaml(int $level=1, int $spaces=2) {
    $extract = [];
    if (!$this->extractAsYaml)
      return;
    foreach (array_keys($this->extractAsYaml) as $id) {
      $extract[$id] = $this->$id;
    }
    echo Yaml::dump(['showExtractAsYaml()'=> $extract], $level, $spaces);
    $this->extractAsYaml = null;
  }
  
  static function test() {
    echo '<pre>';
    if (0) {
      //$base = new Base(__DIR__.'/basetest');
      $base = new Base;
      echo '$base='; print_r($base);
      //$base->key = ['valeur test créée le '.date(DATE_ATOM)];
      $base->key = 'valeur test créée le '.date(DATE_ATOM)  ;
      //$base->writeAsYaml(__DIR__.'/basetest', ['title'=> "Base test"]);
      $base->writeAsYaml();
      $base->save(__DIR__.'/basetest', ['title'=> "Base test"]);
      //$base->save();
    }
    if (0) {
      $base = new Base('', new Criteria(['not']));
      $base->key = ['valeur test créée le '.date(DATE_ATOM)];
      $base->writeAsYaml();
      echo "$base\n";
    }
    if (0) {
      $base = new Base;
      $key = 256;
      $base->$key = ['valeur test créée le '.date(DATE_ATOM)];
      $base->writeAsYaml();
    }
    if (1) {
      $base = new Base;
      $key = 256;
      $base->$key = ['valeur test créée le '.date(DATE_ATOM)];
      $base->mergeToRecord($key, ['autre valeur à merger']);
      $key2 = 257;
      $base->mergeToRecord($key2, ['autre valeur à merger']);
      echo "$base\n";
    }
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


require_once __DIR__.'/../../vendor/autoload.php';

// Tests unitaires des classe Verbose et Base
echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>test Base</title></head><body>\n";

if (!isset($_GET['action'])) {
  echo "<a href='?action=TestCriteria'> Test de la classe Criteria</a><br>\n";
  echo "<a href='?action=TestBase'> Test de la classe Base</a><br>\n";
  die();
}

if ($_GET['action'] == 'TestCriteria') { // Test de la classe Criteria
  Criteria::test();
  die("Fin Criteria::test()\n");
}

if ($_GET['action'] == 'TestBase') { // Test de la classe Base 
  Base::test();
  die("Fin Base::test()\n");
}

