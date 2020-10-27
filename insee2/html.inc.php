<?php
/*PhpDoc:
name: html.inc.php
title: html.inc.php - affichage Html
doc: |
journal: |
  20/10/2020:
    - création
includes:
screens:
classes:
functions:
*/

class Html {
  // $params: ['headers'=> [string]?],  $rows: [[ CELL ]]
  static function table(array $params, array $rows): string {
    return "<table border=1>"
        .Html::headers($params['headers'] ?? [])
        .Html::rows($rows)
        ."</table>\n";
  }
  
  private static function headers(array $headers): string { return $headers ? '<th>'.implode('</th><th>', $headers).'</th>' : ''; }
  
  // $rows: [[ CELL ]]
  private static function rows(array $rows): string {
    $html = '';
    foreach ($rows as $row)
      $html .= '<tr>'.Html::cells($row)."</tr>\n";
    return $html;
  }
  
  // $cells: [ CELL ] / CELL ::= scalar | ['colspan'=> int?, 'value'=> scalar]
  private static function cells(array $cells): string  {
    $html = '';
    foreach ($cells as $cell) {
      if (is_scalar($cell))
        $html .= "<td>$cell</td>";
      else
        $html .= '<td'
          .(isset($cell['colspan']) ? " colspan=$cell[colspan]": '')
          .'>'
          .$cell['value']
          .'</td>';
    }
    return $html;
  }
    
  static function bold(string $str): string { return "<b>$str</b>"; }
};

