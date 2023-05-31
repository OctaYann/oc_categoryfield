<?php

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

if (!defined('_PS_VERSION_')) {
  exit;
}

class CategoryFields extends ObjectModel
{
  /** @var int Object id */
  public $id;
  /** @var int Identifiant de la catégorie prestashop */
  public $id_category;
  /** @var string Seconde description*/
  public $second_description;

  public static $definition = [
    'table' => 'oc_categoryfield',
    'primary' => 'id_category_extra',
    'multilang' => true,
    'multilang_shop' => true,
    'fields' => [
      'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'length' => 10],
      'second_description' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml', 'lang' => true],
    ]
  ];

  /**
   * Récupération de l'identifiant de l'entité via l'identifiant de catégorie
   * @param int $id_category
   * @return false|string|null
   */
  public static function getIdByCategoryId(int $id_category)
  {
    return Db::getInstance()->getValue(
      (new DbQuery())
        ->select(self::$definition['primary'])
        ->from(self::$definition['table'])
        ->where('id_category=' . $id_category)
    );
  }

  /**
   * Installation du modèle
   * A ajouter dans l'installation du module
   */
  public static function installSql(): bool
  {
    try {
      //Création de la table avec les champs communs
      $createTable = Db::getInstance()->execute(
        "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "oc_categoryfield`(
                `id_category_extra` int(10)  NOT NULL AUTO_INCREMENT,
                `id_category` INT(10) NOT NULL,
                PRIMARY KEY (`id_category_extra`)
                ) ENGINE=InnoDB DEFAULT CHARSET=UTF8;"
      );
      //Création de la table des langues
      $createTableLang = Db::getInstance()->execute(
        "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "oc_categoryfield_lang`(
                `id_category_extra` int(10)  NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) NOT NULL DEFAULT '1',
                `id_lang` INT(10) NOT NULL,
                `second_description` TEXT,
                PRIMARY KEY (`id_category_extra`,`id_shop`,`id_lang`)
                ) ENGINE=InnoDB DEFAULT CHARSET=UTF8;"
      );
    } catch (PrestaShopException $e) {
      return false;
    }

    return $createTable && $createTableLang;
  }

  /**
   * Suppression des tables du modules
   * @return bool
   */
  public static function uninstallSql()
  {
    return Db::getInstance()->execute("DROP TABLE IF EXISTS " . _DB_PREFIX_ . "oc_categoryfield")
      && Db::getInstance()->execute("DROP TABLE IF EXISTS " . _DB_PREFIX_ . "oc_categoryfield_lang");
  }
}
