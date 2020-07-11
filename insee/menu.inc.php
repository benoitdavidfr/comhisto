<?php
/*PhpDoc:
name: menu.inc.php
title: menu.inc.php - classe Menu
classes:
*/

{/*PhpDoc: classes
name: Menu
title: class Menu - affiche le menu en CLI ou en HTML et traduit les paramètres CLI en $_GET en fonction du menu
doc: |
  Doit être initialisé avec le Menu dans le format
    [{action} => [
      'argNames' => [{argName}], // liste des noms des paramètres de la commande utilisés en HTTP
      'actions'=> [  // liste d'actions proposées
        {label}=> [{argValue}] // étiquette de chaque action et liste des paramètres de la commande
      ]
    ]]
*/}
class Menu {
  protected $cmdes; // [{action} => [ 'argNames' => [{argName}], 'actions'=> [{label}=> [{argValue}]] ]]
  protected $argv0; // == $argv[0]
  
  function __construct(array $cmdes, int $argc, array $argv) {
    $this->cmdes = $cmdes;
    foreach ($cmdes as $action => $cmde) {
      if (!isset($cmde['argNames']))
        die("Erreur pas de champ 'argNames' pour l'action '$action'");
      if (!is_array($cmde['argNames']))
        die("Erreur 'argNames' pour l'action '$action' n'est pas un array");
      if (!isset($cmde['actions']))
        die("Erreur pas de champ 'actions' pour l'action '$action'");
      if (!is_array($cmde['actions']))
        die("Erreur 'actions' pour l'action '$action' n'est pas un array");
      foreach ($cmde['actions'] as $label => $argValues)
        if (count($argValues) <> count($cmde['argNames']))
          die("Erreur pour action='$action', l'action \"$label\" est mal définie");
    }
    if (php_sapi_name() == 'cli') { // traite le cas d'utilisation en cli, traduit les args CLI en $_GET en fonction de $menu
      $_GET = $this->cli($argc, $argv);
    }
  }
  
  // cas d'utilisation en cli, traduit les args CLI en $_GET en fonction de $this->actions
  function cli(int $argc, array $argv): array {
    //echo "argc=$argc, argv="; print_r($argv);
    $this->argv0 = array_shift($argv); // le nom du fichier php
    if ($argc == 1) {
      return [];
    }
    $_GET = ['action' => array_shift($argv)];
    if (!isset($this->cmdes[$_GET['action']]))
      return $_GET;
    foreach ($argv as $i => $arg) {
      $pname = $this->cmdes[$_GET['action']]['argNames'][$i];
      $_GET[$pname] = $arg;
    }
    //print_r($_GET); die();
    return $_GET;
  }
  
  // affiche le menu en CLI ou en HTML
  function show() {
    if (php_sapi_name() == 'cli') {
      echo "Actions possibles:\n";
      foreach($this->cmdes as $action => $cmde) {
        echo "  php $this->argv0 $action",
          $cmde['argNames'] ? " {".implode('} {', $cmde['argNames'])."}" : '',"\n";
        foreach ($cmde['actions'] as $label => $argValues)
          echo "   # $label\n    php $this->argv0 $action ",implode(' ', $argValues),"\n";
      }
    }
    else {
      echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>menu</title></head><body>Menu:<ul>\n";
      foreach($this->cmdes as $action => $cmde) {
        echo "<li>$action<ul>\n";
        foreach ($cmde['actions'] as $label => $argValues) {
          $href = "?action=$action";
          foreach ($cmde['argNames'] as $argNo => $argName)
            $href .= "&amp;$argName=".urlencode($argValues[$argNo]);
          echo "<li><a href='$href'>$label</a></li>\n";
        }
        echo "</ul>\n";
      }
      echo "</ul>\n";
    }
  }
};
