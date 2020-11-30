<?php
/*PhpDoc:
name:  log.inc.php
title: log.inc.php - enregistrement d'un log
functions:
doc: |
  fonction d'enregistrement d'un log
journal: |
  15/12/2018:
    ajout de la création de la table si elle n'existe pas
    de plus dans openMySQL() sur localhost la base est créée si elle n'existe pas
  20/7/2017:
    suppression de l'utilisation du champ phpserver
includes: [../lib/mysql.inc.php]
*/
require_once __DIR__.'/../lib/mysql.inc.php';

/*PhpDoc: functions
name:  write_log
title: function write_log($access) - enregistrement d'un log
doc: |
  Fonction d'enregistrement d'un log.
  Le paramètre est retourné.
*/
//function write_log(bool $access): bool { return $access; }

function write_log(bool $access): bool {
  $cookiename = 'shomusrpwd';
  
  // si les paramètres MySql ne sont pas définis pour HTTP_HOST alors le log est désactivé
  if (!isset(config('mysqlParams')[$_SERVER['HTTP_HOST']])) {
    echo "Les paramètres MySql ne sont pas définis pour HTTP_HOST=$_SERVER[HTTP_HOST] alors le log est désactivé<br>\n";
    return $access;
  }
  
//  echo "<pre>"; print_r($_SERVER); die();
  $login = (isset($_COOKIE[$cookiename]) ? "'".substr($_COOKIE[$cookiename], 0, strpos($_COOKIE[$cookiename], ':'))."'" : 'NULL');
  $user = (isset($_SERVER['PHP_AUTH_USER']) ? "'".$_SERVER['PHP_AUTH_USER']."'" : 'NULL');
  //  $phpserver = json_encode($_SERVER);
  $referer = (isset($_SERVER['HTTP_REFERER']) ? "'$_SERVER[HTTP_REFERER]'" : 'NULL');
  // Creation d'une enregistrement dans le log
  $sql = "insert into log(logdt, ip, referer, login, user, host, request_uri, access) "
        ."values (now(), '$_SERVER[REMOTE_ADDR]', $referer, $login, $user, '$_SERVER[HTTP_HOST]', '$_SERVER[REQUEST_URI]',"
                ." '".($access?'T':'F')."')";
  //  echo "<pre>",$sql,"</pre>\n";
  try {
    $mysqlParams = config('mysqlParams')[$_SERVER['HTTP_HOST']];
    MySQL::open($mysqlParams);
  }
  catch (Exception $e) {
    throw new Exception("Connexion MySql impossible avec les paramètres $mysqlParams");
  }
  try {
    MySql::query($sql);
  }
  catch (Exception $e) {
    if (preg_match("!Table 'comhisto.log' doesn't exist!", $e->getMessage())) {
      $sql_create_table = "create table log(
          logdt datetime not null comment 'date et heure',
          ip varchar(255) not null comment 'adresse IP appelante',
          referer longtext comment 'referer appelant',
          login varchar(255) comment 'login appelant éventuel issu du cookie',
          user varchar(255) comment 'login appelant éventuel issu de l\'authentification HTTP',
          host longtext comment 'hote sur lequel est appelée la requête',
          request_uri longtext comment 'requete appelée sans le host',
          access char(1) comment 'acces accordé T ou refusé F'
        )";
      MySql::query($sql_create_table);
      MySql::query($sql);
    }
    else
      throw new Exception ($e->getMessage());
  }
  return $access;
}
