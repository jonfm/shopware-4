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
 * @package    Shopware_Controllers_Widgets
 * @subpackage Widgets
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Shopware Application
 *
 * todo@all: Documentation
 */

class Shopware_Controllers_Widgets_Index extends Enlight_Controller_Action
{
    /**
     * Pre dispatch method
     */
    public function preDispatch()
    {
        //$this->View()->setCaching(false);
        if($this->Request()->getActionName() == 'refreshStatistic') {
            $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        }
    }

    /**
     * Sets a template variable with the last views articles.
     *
     * @return void
     */
    public function lastArticlesAction()
    {
        $articleId = (int) $this->Request()->getParam('sArticle');
        $articles = Shopware()->Modules()->Articles()->sGetLastArticles($articleId);
        $this->View()->assign('sLastArticles', $articles, true);

        $plugin = Shopware()->Plugins()->Frontend()->LastArticles();
        if(!empty($articleId)) {
            $plugin->setLastArticleById($articleId);
        }
    }

    /**
     * Refresh shop statistic
     */
    public function refreshStatisticAction()
    {
        $request = $this->Request();
        $response = $this->Response();

        /** @var $plugin Shopware_Plugins_Frontend_Statistics_Bootstrap */
        $plugin = Shopware()->Plugins()->Frontend()->Statistics();
        $plugin->updateLog($request, $response);

        if(($articleId = $request->getParam('articleId')) !== null) {
            $plugin = Shopware()->Plugins()->Frontend()->LastArticles();
            $plugin->setLastArticleById($articleId);
        }
    }

    /**
     * Get cms menu
     */
    public function menuAction()
    {
        if(!$this->View()->isCached()) {
            $this->View()->sGroup = $this->Request()->getParam('group');
            $plugin = Shopware()->Plugins()->Core()->ControllerBase();
            $this->View()->sMenu = $plugin->getMenu();
        }
    }

    /**
     * Get shop menu
     */
    public function shopMenuAction()
    {
        if(!$this->View()->isCached()) {
            $shop = Shopware()->Shop();
            $main = $shop->getMain() !== null ? $shop->getMain() : $shop;

            $this->View()->shop = $shop;
            $this->View()->currencies = $shop->getCurrencies();
            $languages = $shop->getChildren()->toArray();
            foreach($languages as $languageKey => $language) {
                if(!$language->getActive()) {
                    unset($languages[$languageKey]);
                }
            }
            array_unshift($languages, $main);
            $this->View()->languages = $languages;
        }
    }
}
