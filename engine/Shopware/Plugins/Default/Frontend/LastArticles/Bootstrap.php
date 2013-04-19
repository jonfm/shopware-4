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
 * @package    Shopware_Plugins
 * @subpackage LastArticles
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Shopware LastArticles Plugin
 *
 * todo@all: Documentation
 */
class Shopware_Plugins_Frontend_LastArticles_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Install plugin method
     *
     * @return bool
     */
    public function install()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch',
            'onPostDispatch'
        );

        $form = $this->Form();
        $parent = $this->Forms()->findOneBy(array('name' => 'Frontend'));
        $form->setParent($parent);
        $form->setElement('checkbox', 'show', array(
            'label' => 'Artikelverlauf anzeigen',
            'value' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'controller', array(
            'label' => 'Controller-Auswahl',
            'value' => 'index, listing, detail, custom, newsletter, sitemap, campaign',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('number', 'thumb', array(
            'label' => 'Vorschaubild-Größe',
            'value' => 2,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('number', 'time', array(
            'label' => 'Speicherfrist in Tagen',
            'value' => 15
        ));

        return true;
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return array(
            'label' => 'Artikelverlauf'
        );
    }

    /**
     * Event listener method
     *
     * Read the last article in defined controllers
     * Saves the last article in detail controller
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatch(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $response = $args->getSubject()->Response();
        $view = $args->getSubject()->View();

        if (!$request->isDispatched()
            || $response->isException()
            || $request->getModuleName() != 'frontend'
            //|| !empty(Shopware()->Session()->Bot)
            || !$view->hasTemplate()
        ) {
            return;
        }

        $config = $this->Config();

        if ($request->getControllerName() == 'detail'
            && !Shopware()->Session()->Bot
            && Shopware()->Shop()->getTemplate()->getVersion() == 1
        ) {
            $this->setLastArticle($view->sArticle);
        }

        if (rand(0, 100) === 0) {
            $time = $config->time > 0 ? (int)$config->time : 15;
            $sql = '
                DELETE FROM s_emarketing_lastarticles
                WHERE time < DATE_SUB(CONCAT(CURDATE(), ?), INTERVAL ? DAY)
            ';
            Shopware()->Db()->query($sql, array(' 00:00:00', $time));
        }

        if (empty($config->show)) {
            return;
        }
        if (!empty($config->controller)
            && strpos($config->controller, $request->getControllerName()) === false
        ) {
            return;
        }

        $view->assign('sLastArticlesShow', true);
    }

    /**
     * @param int $articleId
     */
    public function setLastArticleById($articleId)
    {
        $articleId = (int) $articleId;
        /** @var $module \sArticles */
        $module = Shopware()->Modules()->Articles();
        $article = array(
            'articleID' => $articleId,
            'image' => $module->sGetArticlePictures($articleId, true),
            'articleName' => $module->sGetArticleNameByArticleId($articleId)
        );
        $this->setLastArticle($article);
    }

    /**
     * @param array $article
     */
    public function setLastArticle($article)
    {
        $config = $this->Config();
        $thumb = $config->thumb !== null ? (int)$config->thumb : (int)Shopware()->Config()->lastArticlesThumb;

        Shopware()->Modules()->Articles()->sSetLastArticle(
            isset($article['image']['src'][$thumb]) ? $article['image']['src'][$thumb] : null,
            $article['articleName'],
            $article['articleID']
        );

        Shopware()->Session()->sLastArticle = $article['articleID'];
    }
}
