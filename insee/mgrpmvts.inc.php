<?php
/*PhpDoc:
name: mgrpmvts.inc.php
title: mgrpmvts.inc.php - définition de la classe MultiGroupMvts
doc: |
  La classe MultiGroupMvts gère les 6 cas où sur une même entité 2 GroupMvts sont concomitants.
  Si on ne traite pas ces cas, dans addToRpicom() les effets d'un des 2 GroupMvts sont écrasés par l'autre.

  Il y a 6 MultiGroupMvts dans le fichier des mvts de 2020 qui génèrent des entrées bis dans le Rpicom:
    création d'un bis sur 44225/2018-01-01
    création d'un bis sur 49382/2016-01-01
    création d'un bis sur 55273/1983-01-01
    création d'un bis sur 55386/1983-01-01
    création d'un bis sur 51369/1966-12-12
    création d'un bis sur 51440/1950-06-17

journal: |
  23/4/2020:
    - création
functions:
classes:
*/
{/*PhpDoc: classes
name: MultiGroupMvts
title: class MultiGroupMvts - Chaque MultiGroupMvts correspond à 2 GroupMvts concomitants sur une même entité
doc: |
methods:
*/}
class MultiGroupMvts {
  protected $groups; // [ mod => GroupMvts ]
  
  function __construct(array $groupOfMvts=[]) {
    if (!$groupOfMvts) {
      $this->groups = [];
      return;
    }
    $groupPerMod = [];
    foreach ($groupOfMvts as $mvt) {
      addValToArray($mvt, $groupPerMod[$mvt['mod']]);
    }
    foreach ($groupPerMod as $mod => $mvts) {
      $this->groups[$mod] = new GroupMvts($mvts);
    }
    if (count($this->groups) != 2)
      throw new Exception("Erreur MultiGroupMvts limité à 2 groupes");
    
    // ordonancement des groupes par ordre chronologique 0 doit s'effectuer avant 1
    $mods = array_keys($this->groups);
    $grp0 = $this->groups[$mods[0]];
    $grp1 = $this->groups[$mods[1]];
    if ($grp1->isBefore($grp0) && $grp0->isBefore($grp1))
      throw new Exception("Erreur d'ordonnancement sur $grp0 et $grp1");
    if ($grp1->isBefore($grp0)) { // si 1 est avant 0 alors je les inverse
      $this->groups = [
        $mods[1] => $grp1,
        $mods[0] => $grp0,
      ];
    }
  }
  
  function mod(): string { return implode('+', array_keys($this->groups)); }
  
  function asArray(): array {
    $array = [];
    foreach ($this->groups as $mod => $group)
      $array[$mod] = $group->asArray();
    return $array;
  }
  
  function ids(): array {
    $ids = [];
    foreach ($this->groups as $group)
      foreach ($group->ids() as $id)
        $ids[$id] = 1;
    return array_keys($ids);
  }
  
  // liste des names concernés
  function names(): array {
    $names = [];
    foreach ($this->groups as $group)
      foreach ($group->names() as $name)
        $names[$name] = 1;
    return array_keys($names);
  }
  
  function show() {
    echo "<b>MultiGroupMvts</b>\n";
    echo "<table border=1>\n";
    foreach ($this->groups as $mod => $group) {
      echo "<tr><td>$mod</td><td>";
      $group->show();
      echo "</td></tr>\n";
    }
    echo "</table>\n";
  }
  
  function mvtsPattern(Criteria $trace): array {
    $pattern = [];
    foreach ($this->groups as $mod => $group) {
      $pattern[$mod] = $group->mvtsPattern($trace);
    }
    return $pattern;
  }
  
  // génère un nouvel objet contenant les factAvDefact() des 2 GroupMvts
  function factAvDefact(array $factAv=[]): MultiGroupMvts {
    $factAvDefact = new MultiGroupMvts();
    foreach ($this->groups as $mod => $group) {
      $factAvDefact->groups[$mod] = $group->factAvDefact();
    }
    return $factAvDefact;
  }
  
  function addToRpicom(Base $rpicom, Criteria $trace): void {
    foreach (array_reverse($this->groups) as $mod => $group)
      $group->addToRpicom($rpicom, $trace);
  }
  
  function compte(Criteria $trace): array {
    {/*PhpDoc: methods
    name: compte
    title: "compte(array $comptes, Criteria $trace): array - compte"
    doc: |
    */}
    $groups = $this->groups;
    $group0 = array_shift($groups);
    $compte = $group0->compte($trace);
    foreach ($groups as $group) {
      $compte2 = $group->compte($trace);
      foreach ($compte2 as $k => $v) {
        if (isset($compte[$k]))
          $compte[$k] += $v;
        else
          $compte[$k] = $v;
      }
    }
    return $compte;
  }
};