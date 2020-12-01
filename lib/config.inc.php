<?php
/*PhpDoc:
name: config.inc.php
title: config.inc.php - fichier de config par défaut
doc: |
  Le vrai fichier de config est secretconfig.inc.php qui contient des infos confidentielles
  S'il existe, c'est lui qui est utilisé
  Sinon ce fichier congtient une configuration par défaut
journal: |
  23/5/2020:
    ajout du contrôle IPv6
  9/11/2019:
    amélioration du controle d'accès
includes: [secretconfig.inc.php]
*/
if (is_file(__DIR__.'/secretconfig.inc.php'))
  require_once __DIR__.'/secretconfig.inc.php';
else {
  // Accès à une des rubriques du fichier de config
  function config(string $rubrique): array {
    static $config = [
      # Paramétrage du serveur MySQL pour enregistrer les logs en fonction du serveur hébergeant l'application
      # Le nom_du_serveur est défini par $_SERVER['HTTP_HOST']
      'mysqlParams'=> [
        'nom_du_serveur'=> 'mysql://{user}:{passwd}@{host}/{database}',
      ],
    ];

    return isset($config[$rubrique]) ? $config[$rubrique] : [];
  };
}
