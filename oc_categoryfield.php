<?php

/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use Doctrine\DBAL\Tools\Dumper;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShopBundle\Form\Admin\Type\TranslateType;
use PrestaShopBundle\Form\Admin\Type\FormattedTextareaType;

require_once __DIR__ . '/classes/CategoryFields.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Oc_categoryfield extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'oc_categoryfield';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Octacom';
        $this->need_instance = 1;

        parent::__construct();

        $this->displayName = $this->l('Octacom category field');
        $this->description = $this->l('Add a description field in category administration form');

        $this->confirmUninstall = $this->l('Etes vous sûr de désinstaller le module ? Cela supprimera les champs et leur contenu définitivement.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionAfterCreateCategoryFormHandler') &&
            $this->registerHook('actionAfterUpdateCategoryFormHandler') &&
            $this->registerHook('actionCategoryFormBuilderModifier') &&
            $this->registerHook('filterCategoryContent') &&
            CategoryFields::installSql();
    }

    public function uninstall()
    {
        return parent::uninstall() && CategoryFields::uninstallSql();
    }

    /**
     * Fonction basique de log
     * @param string $message
     * @return void
     */
    protected function log($message): void
    {
        if (!is_dir(dirname(__FILE__) . '/logs/')) {
            mkdir(dirname(__FILE__) . '/logs/');
        }

        file_put_contents(
            dirname(__FILE__) . '/logs/debug.log',
            date('Y-m-d H:i:s') . " - " . $message . "\n",
            FILE_APPEND
        );
    }

    /**
     * Récupération des informations spécifique de l'objet
     * @param int|null $id_category
     * @return array
     */
    protected function getCustomFieldsValue($id_category): array
    {
        try {
            if (!$id_category) {
                return [
                    'second_description' => '',
                ];
            }
            $idCategoryField = CategoryFields::getIdByCategoryId($id_category);
            $categoryField = new CategoryFields($idCategoryField);
            return [
                'second_description' => $categoryField->second_description,
            ];
        } catch (PrestaShopException $e) {
            $this->log($e->getMessage());
            return [
                'second_description' => '',
            ];
        }
    }

    /**
     * Récupération des informations spécifiques de la catégorie
     *
     * @param int $id_category
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getCustomCategoryFields(int $id_category): array
    {
        $return = [];

        if (!$id_category) {
            return $return;
        }

        $idCategoryField = CategoryFields::getIdByCategoryId($id_category);

        if (!$idCategoryField) {
            return $return;
        }

        $categoryField = new CategoryFields($idCategoryField, $this->context->language->id);
        $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Object\ObjectPresenter();
        $return = $presenter->present($categoryField);
        unset($return['id_category']);
        unset($return['id']);
        return $return;
    }

    /**
     * Ajout de contenu de catégorie sans surcharge
     * @param array $params
     * @return array
     */
    public function hookFilterCategoryContent(array $params)
    {
        $catId = $params['object']['id'];
        if (!$catId) {
            return [];
        }
        $additional = $this->getCustomCategoryFields($params['object']['id']);
        if (count($additional)) {
            $params['object'] = array_merge($params['object'], $additional);
            return [
                'object' => $params['object']
            ];
        }
    }

    public function hookActionCategoryFormBuilderModifier(array $params)
    {
        try { //Récupération des informations des champs custom
            $customFieldsValues = $this->getCustomFieldsValue($params['id']);
            $locales = $this->get('prestashop.adapter.legacy.context')->getLanguages();

            //Récupération du form builder
            /** @var \Symfony\Component\Form\FormBuilder $formBuilder */
            $formBuilder = $params['form_builder'];

            //Ajout de notre champ spécifique
            $formBuilder->add(
                $this->name . '_second_description',
                TranslateType::class,
                [
                    'type' => FormattedTextareaType::class,
                    'label' => $this->l('Second description'), //Label du champ
                    'locales' => $locales,
                    'hideTabs' => false,
                    'required' => false,
                    'data' => !!$customFieldsValues['second_description'] ? $customFieldsValues['second_description'] : null,
                    'options' => [
                        'constraints' => [
                            new CleanHtml([
                                'message' => $this->trans('This field is invalid', ['Admin.Notifications.Error']),
                            ]),
                        ],
                    ],
                ]
            );
            $formBuilder->setData($params['data']);
        } catch (Exception $e) {
            $this->log('Error :' . $e->getMessage());
            $this->log('Error string :' . $e->getTraceAsString());
        }
    }


    /**
     * Action effectuée après la création d'une catégorie
     * @param array $params
     */
    public function hookActionAfterCreateCategoryFormHandler(array $params)
    {
        $this->updateData($params['id'], $params['form_data']);
    }

    /**
     * Action effectuée après la mise à jour d'une catégorie
     * @param array $params
     */
    public function hookActionAfterUpdateCategoryFormHandler(array $params)
    {
        $this->updateData($params['id'], $params['form_data']);
    }

    /**
     * Fonction qui va effectuer la mise à jour
     * @param int $id_category
     * @param array $data
     * @return void
     */
    protected function updateData(int $id_category, array $data): void
    {
        try {
            $idCategoryField = CategoryFields::getIdByCategoryId($id_category);
            $categoryField = new CategoryFields($idCategoryField);
            $categoryField->id_category = $id_category;

            foreach ($data as $key => $value) {
                if (strpos($key, $this->name) !== false) {
                    $objectKey = str_replace($this->name . '_', '', $key);
                    $categoryField->$objectKey = $value;
                }
            }

            $categoryField->save();
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }
    }
} 
