<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Components
 * @subpackage Plugin
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Shopware Plugin Bootstrap
 *
 * todo@all: Documentation
 *
 * @method Shopware Application()
 * @method Shopware_Components_Plugin_Namespace Collection()
 */
abstract class Shopware_Components_Plugin_Bootstrap extends Enlight_Plugin_Bootstrap_Config
{
    /**
     * @var Enlight_Config
     */
    protected $info;

    /**
     * @var Shopware\Models\Plugin\Plugin
     */
    protected $plugin;

    /**
     * @var Shopware\Models\Config\Form
     */
    protected $form;

    /**
     * @var Shopware_Components_Plugin_Namespace
     */
    protected $collection;

    /**
     * Constructor method
     *
     * @param $name
     * @param Enlight_Config|null $info
     */
    public function __construct($name, $info = null)
    {
        $this->info = new Enlight_Config($this->getInfo(), true);
        if ($info instanceof Enlight_Config) {
            $info->setAllowModifications(true);
            $updateVersion = null;
            $updateSource = null;

            if ($this->hasInfoNewerVersion($this->info, $info)) {
                $updateVersion = $this->info->get('version');
                $updateSource = $this->info->get('source');
            }

            $this->info->merge($info);
            if ($updateVersion !== null) {
                $this->info->set('updateVersion', $updateVersion);
                $this->info->set('updateSource', $updateSource);
            }
        }
        $this->info->set('capabilities', $this->getCapabilities());
        parent::__construct($name);
    }

    /**
     * Returnswhether or not $updatePluginInfo contains a newer version than $currentPluginInfo
     *
     * @param \Enlight_Config $currentPluginInfo
     * @param \Enlight_Config $updatePluginInfo
     * @return bool
     */
    public function hasInfoNewerVersion(Enlight_Config $updatePluginInfo, Enlight_Config $currentPluginInfo)
    {
        $currentVersion = $currentPluginInfo->get('version');
        $updateVersion = $updatePluginInfo->get('version');

        if (empty($updateVersion)) {
            return false;
        }

        // Exception for Pre-Installed Plugins
        if ($currentVersion == "1" && $updateVersion == "1.0.0") {
            return false;
        }

        return version_compare($updateVersion, $currentVersion, '>');
    }

    /**
     * Install plugin method
     *
     * @return bool
     */
    public function install()
    {
        return !empty($this->info->capabilities['install']);
    }

    /**
     * Uninstall plugin method
     *
     * @return bool
     */
    public function uninstall()
    {
        return !empty($this->info->capabilities['install']);
    }

    /**
     * Update plugin method
     *
     * @param string $version
     * @return bool
     */
    public function update($version)
    {
        if (empty($this->info->capabilities['update']) || empty($this->info->capabilities['install'])) {
            return false;
        }
        return $this->install();
    }

    /**
     * Enable plugin method
     *
     * @return bool
     */
    public function enable()
    {
        return !empty($this->info->capabilities['enable']);
    }

    /**
     * Disable plugin method
     *
     * @return bool
     */
    public function disable()
    {
        return !empty($this->info->capabilities['enable']);
    }

    /**
     * @return Enlight_Config
     */
    public final function Info()
    {
        return $this->info;
    }

    /**
     * @return string
     */
    public final function Path()
    {
        return $this->info->path;
    }

    /**
     * Returns plugin config
     *
     * @return \Enlight_Config
     */
    public function Config()
    {
        return $this->Collection()->getConfig($this->name);
    }

    /**
     * @return Shopware\Models\Plugin\Plugin
     */
    public final function Plugin()
    {
        if($this->plugin === null) {
            $repo = Shopware()->Models()->getRepository(
                'Shopware\Models\Plugin\Plugin'
            );
            $this->plugin = $repo->findOneBy(array(
                'id' => $this->getId()
            ));
        }
        return $this->plugin;
    }

    /**
     * @return Shopware\Components\Model\ModelRepository
     */
    public final function Forms()
    {
        return Shopware()->Models()->getRepository(
            'Shopware\Models\Config\Form'
        );
    }

    /**
     * Returns plugin form
     *
     * @return Shopware\Models\Config\Form
     */
    public final function Form()
    {
        if(!$this->hasForm()) {
            $this->form = $this->initForm();
        }
        return $this->form;
    }

    /**
     * @return bool
     */
    public final function hasForm()
    {
        if($this->form === null && $this->getName() !== null) {
            $formRepository = $this->Forms();
            $this->form = $formRepository->findOneBy(array(
                'name' => $this->getName()
            ));
        }
        if($this->form === null && $this->getId() !== null) {
            $formRepository = $this->Forms();
            $this->form = $formRepository->findOneBy(array(
                'pluginId' => $this->getId()
            ));
        }
        return $this->form !== null;
    }

    /**
     * @return Shopware\Models\Config\Form
     */
    private function initForm()
    {
        $info = $this->Info();
        $formRepository = $this->Forms();
        $form  = new \Shopware\Models\Config\Form;
        $form->setPluginId($this->getId());
        $form->setName($info->name);
        $form->setLabel($info->label);
        $form->setDescription($info->description);
        $parent = $formRepository->findOneBy(array(
            'name' => strpos($this->name, 'Payment') !== false ? 'Payment' : 'Other'
        ));
        $form->setParent($parent);
        $this->Application()->Models()->persist($form);
        return $form;
    }

    /**
     * Returns shopware menu
     *
     * @return Shopware\Models\Menu\Repository
     */
    public final function Menu()
    {
        return Shopware()->Models()->getRepository(
            'Shopware\Models\Menu\Menu'
        );
    }

    /**
     * Create a new menu item instance
     *
     * @param array $options
     * @return Shopware\Models\Menu\Menu|null
     */
    public function createMenuItem(array $options)
    {
        if(!isset($options['label'])) {
            return null;
        }
        if(isset($options['parent'])
          && $options['parent'] instanceof \Shopware\Models\Menu\Menu) {
            $parentId = $options['parent']->getId();
        } else {
            $parentId = null;
            unset($options['parent']);
        }
        $item = $this->Menu()->findOneBy(array(
            'label' => $options['label'],
            'parentId' => $parentId
        ));
        if($item === null) {
            $item = new Shopware\Models\Menu\Menu();
        }
        $item->fromArray($options);
        $plugin = $this->Plugin();
        $plugin->getMenuItems()->add($item);
        $item->setPlugin($plugin);
        return $item;
    }

    /**
     * @return Shopware\Components\Model\ModelRepository
     */
    public final function Payments()
    {
        return Shopware()->Models()->getRepository(
            'Shopware\Models\Payment\Payment'
        );
    }

    /**
     * Create a new payment instance
     *
     * @param   array $options
     * @param   null $description
     * @param   null $action
     * @return  \Shopware\Models\Payment\Payment
     */
    public function createPayment($options, $description = null, $action = null)
    {
        if(is_string($options)) {
            $options = array('name' => $options);
        }
        $payment = $this->Payments()->findOneBy(array(
            'name' => $options['name']
        ));
        if($payment === null) {
            $payment = new \Shopware\Models\Payment\Payment();
            $payment->setName($options['name']);
            $this->Application()->Models()->persist($payment);
        }
        $payment->fromArray($options);
        if($description !== null) {
            $payment->setDescription($description);
        }
        if($action !== null) {
            $payment->setAction($action);
        }
        $plugin = $this->Plugin();
        $plugin->getPayments()->add($payment);
        $payment->setPlugin($plugin);
        Shopware()->Models()->flush($payment);
        return $payment;
    }

    /**
     * Create a new template
     *
     * @param   array|string $options
     * @return  \Shopware\Models\Shop\Template
     */
    public function createTemplate($options)
    {
        if(is_string($options)) {
            $options = array('template' => $options);
        }
        $template = $this->Payments()->findOneBy(array(
            'template' => $options['template']
        ));
        if($template === null) {
            $template = new \Shopware\Models\Shop\Template();
            if(!isset($options['name'])) {
                $options['name'] = ucfirst($options['template']);
            }
        }
        $template->fromArray($options);
        $plugin = $this->Plugin();
        $plugin->getTemplates()->add($template);
        $template->setPlugin($plugin);
        return $template;
    }

    /**
     * Create cron job method
     */
    public function createCronJob($name, $action, $interval = 86400, $active = 1)
    {
        $sql = '
			INSERT INTO s_crontab (`name`, `action`, `next`, `start`, `interval`, `active`, `end`, `pluginID`)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)
		';
        Shopware()->Db()->query($sql, array(
            $name, $action, new Zend_Date(), null, $interval, $active, new Zend_Date(), $this->getId()
        ));
    }

    /**
     * Subscribes a plugin event.
     *
     * {@inheritDoc}
     *
     * @param string|Enlight_Event_Handler $event
     * @param string $listener
     * @param integer  $position
     * @return Enlight_Plugin_Bootstrap_Config
     */
    public function subscribeEvent($event, $listener = null, $position = null)
    {
        if ($listener === null) {
            $this->Collection()->Subscriber()->registerListener($event);
        } else {
            parent::subscribeEvent($event, $listener, $position);
        }
        return $this;
    }

    /**
     * Returns capabilities
     */
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update' => true,
            'enable' => true
        );
    }

    /**
     * Returns plugin id
     *
     * @final
     * @return int
     */
    public function getId()
    {
        return $this->Collection()->getPluginId($this->name);
    }

    /**
     * Returns plugin version
     *
     * @return string
     */
    public function getVersion()
    {
        return null;
    }

    /**
     * Returns plugin name
     *
     * @return string
     */
    public function getLabel()
    {
        return isset($this->info->label) ? $this->info->label : $this->getName();
    }

    /**
     * Returns plugin name
     *
     * @final
     * @return string
     */
    final public function getName()
    {
        return $this->name;
    }

    /**
     * Returns plugin source
     *
     * @final
     * @return string
     */
    final public function getSource()
    {
        return $this->info->source;
    }

    /**
     * Returns plugin info
     *
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel()
        );
    }

    /**
     * @deprecated Will be executed automatically.
     */
    public function deleteForm()
    {

    }

    /**
     * @deprecated Will be executed automatically.
     */
    public function deleteConfig()
    {

    }

    /**
     * @deprecated Use the event subscriber direct
     * @param $event
     * @param $listener
     * @param null $position
     * @return Enlight_Event_Handler_Plugin
     */
    public function createEvent($event, $listener, $position = null)
    {
        $handler = new Enlight_Event_Handler_Plugin(
            $event, $this->collection, $this, $listener, $position
        );
        return $handler;
    }

    /**
     * @deprecated Use the event subscriber (Event: class::method::type)
     * @param   $class
     * @deprecated
     * @param   $method
     * @param   $listener
     * @param   null $type
     * @param   null $position
     * @return  Enlight_Event_Handler_Plugin
     */
    public function createHook($class, $method, $listener, $type = null, $position = null)
    {
        $handler = new Enlight_Event_Handler_Plugin(
            $class . '::' . $method . '::' . $type,
            $this->collection, $this, $listener, $position
        );
        return $handler;
    }

    /**
     * Subscribe hook method
     *
     * @param Enlight_Hook_HookHandler $handler
     * @return Shopware_Components_Plugin_Bootstrap
     */
    public function subscribeHook($handler)
    {
        return $this->subscribeEvent($handler);
    }

    /**
     * Subscribe cron method
     *
     * @deprecated Use the createCronJob method
     */
    public function subscribeCron($name, $action, $interval = 86400, $active = true, $next = null, $start = null, $end = null)
    {
        $this->createCronJob($name, $action, $interval, $active);
    }

    /**
     * Check if a list of given plugins is currently available
     * and active
     * @param array $plugins
     * @return bool
     */
    protected function assertRequiredPluginsPresent(array $plugins)
    {
        foreach ($plugins as $plugin){
            $sql = 'SELECT 1 FROM s_core_plugins WHERE name = ? AND active = 1';
            $test = Shopware()->Db()->fetchOne($sql, array($plugin));
            if (empty($test)){
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a given version is greater or equal to the currently installed version
     * @param  $requiredVersion string Format: 3.5.4 or 3.5.4.21111
     * @return bool
     */
    protected function assertVersionGreaterThen($requiredVersion)
    {
        $version = $this->Application()->Config()->version;
        return version_compare($version, $requiredVersion, '>=');
    }

    /**
     * Register the custom model dir
     */
    protected function registerCustomModels()
    {
        $this->Application()->Loader()->registerNamespace(
            'Shopware\CustomModels',
            $this->Path() . 'Models/'
        );
        $this->Application()->ModelAnnotations()->addPaths(array(
            $this->Path() . 'Models/'
        ));
    }
}
