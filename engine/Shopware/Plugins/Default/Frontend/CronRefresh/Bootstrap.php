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
 * @subpackage CronRefresh
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Stefan Hamann
 * @author     $Author$
 */
/**
 * Shopware Plugin Frontend CronRefresh
 *
 * Plugin to cleanup shopware statistic tables in intervals
 *
 */
class Shopware_Plugins_Frontend_CronRefresh_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
	/**
	 * Defining Cronjob-Events
	 * @return bool
	 */
	public function install()
	{		
		$this->subscribeEvent('Shopware_CronJob_Clearing', 'onCronJobClearing');
		$this->subscribeEvent('Shopware_CronJob_Search', 'onCronJobSearch');
		return true;
	}

	/**
	 * Clear s_emarketing_lastarticles / s_statistics_search / s_core_log in 30 days interval
	 * Delete all entries older then 30 days.
	 * To change this time - modify sql-queries
	 * @static
	 * @param Shopware_Components_Cron_CronJob $job
	 * @return void
	 */
	public static function onCronJobClearing(Shopware_Components_Cron_CronJob $job)
	{
		// Delete all entries from lastarticles older then 30 days
		$sql = '
			DELETE FROM s_emarketing_lastarticles WHERE `time` < date_add(current_date, interval -30 day)
		';
		$result = Shopware()->Db()->query($sql);
		$data['lastarticles']['rows'] = $result->rowCount();

		// Delete all entries from search statistic older then 30 days
		$sql = '
			DELETE FROM s_statistics_search WHERE datum < date_add(current_date, interval -30 day)
		';
		$result = Shopware()->Db()->query($sql);
		$data['search']['rows'] = $result->rowCount();

		// Delete all entries from s_core_log older then 30 days
		$sql = '
			DELETE FROM s_core_log WHERE `date` < date_add(current_date, interval -30 day)
		';
		$result = Shopware()->Db()->query($sql);
		$data['log']['rows'] = $result->rowCount();

        return $data;
	}

	/**
	 * Cleanup / Regenerate Shopware translation table used in search for example
     *
	 * @param Shopware_Components_Cron_CronJob $job
     * @deprecated
	 * @return void
	 */
	public function onCronJobTranslation(Shopware_Components_Cron_CronJob $job)
	{
        // Translations get resolved automatically starting with v4.0
	}

	/**
	 * Recreate shopware search index
     *
	 * @param Shopware_Components_Cron_CronJob $job
	 * @return void
	 */
	public function onCronJobSearch(Shopware_Components_Cron_CronJob $job)
	{
        $adapter = Enlight()->Events()->filter('Shopware_Controllers_Frontend_Search_SelectAdapter',null);
        if (empty($adapter)){
             $adapter = new Shopware_Components_Search_Adapter_Default(Shopware()->Db(), Shopware()->Cache(), new Shopware_Components_Search_Result_Default(), Shopware()->Config());
        }
        //$adapter = new Shopware_Components_Search_Adapter_Default(Shopware()->Db(), Shopware()->Cache(), new Shopware_Components_Search_Result_Default(), Shopware()->Config());
        $adapter->buildSearchIndex();
	}
}