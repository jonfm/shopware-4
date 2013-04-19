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
 * @package    Shopware_Controllers
 * @subpackage Article
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Statistics controller
 *
 * todo@all: Documentation
 */
class Shopware_Controllers_Backend_Analytics extends Shopware_Controllers_Backend_ExtJs
{

	protected function initAcl()
	{
		// read
		$this->addAclPermission('shopList', 'read', 'Insufficient Permissions');
		$this->addAclPermission('sourceList', 'read', 'Insufficient Permissions');
		$this->addAclPermission('orderAnalytics', 'read', 'Insufficient Permissions');
		$this->addAclPermission('visits', 'read', 'Insufficient Permissions');
		$this->addAclPermission('orderDetailAnalytics', 'read', 'Insufficient Permissions');
		$this->addAclPermission('searchAnalytics', 'read', 'Insufficient Permissions');
		$this->addAclPermission('conversionRate', 'read', 'Insufficient Permissions');
	}

    /**
     * Get a list of installed shops
     */
    public function shopListAction()
    {
        $shops = $this->getShops();
        $this->View()->assign(array('data' => $shops, 'success' => true));
    }


    /**
     * Get a tree-column-model compatible list
     * of installed shops
     */
    public function sourceListAction()
    {
        $sql = '
           SELECT
              s.id , s.name as text,
              c.currency AS currency,
              c.name AS currencyName,
              c.templatechar AS currencyChar
            FROM s_core_shops s, s_core_currencies c
            WHERE s.currency_id = c.id
            AND s.main_id IS NULL
            ORDER BY s.default DESC, s.name
        ';
        $shops = Shopware()->Db()->fetchAll($sql);
        foreach ($shops as $index => $shop) {
            $shops[$index]['leaf'] = true;
            $shops[$index]['checked'] = false;
        }

        $this->View()->assign(array(
            'text' => '.',
            'children' => array(
                array('text' => 'Shops', 'expanded' => true, 'children' => $shops),
            ),
            'success' => true
        ));
    }


    /**
     * Get sales data for statistics
     * Kind of statistic is defined via
     * $this->Request()->getParam('type')
     *  Possible values:
     *  dispatch,payment,month,weekday,week,daytime,country
     * Returns json formatted result
     */
    public function orderAnalyticsAction()
    {
        $shops = $this->getShops();
        $fromDate = $this->getFromDate();
        $toDate = $this->getToDate();

        if (!$this->Request()->getParam('tax')) {
            $sqlAmount = 'invoice_amount-invoice_shipping';
        } else {
            $sqlAmount = 'invoice_amount_net-invoice_shipping_net';
        }
        $sqlAmount = '(' . $sqlAmount . ')/currencyFactor';

        $sqlWhere = '';
        $sqlSelect = '';
        $sqlSelectName = 'name';
        $sqlDateSub = '14 DAY';
        $sqlDateTo = "NOW()";
        $sqlSelectField = 'ordertime';

        switch ($this->Request()->getParam('type')) {
            case 'dispatch':
                $sqlSelectField = 'd.name';
                $sqlGroupBy = 'o.dispatchID';
                break;
            case 'payment':
                $sqlSelectField = 'p.description';
                $sqlGroupBy = 'o.paymentID';
                break;
            case 'month':
                $sqlSelectField = "DATE_FORMAT(ordertime, '%Y-%m-01')";
                $sqlSelectName = 'date';
                $sqlDateFrom = "DATE_SUB(DATE_FORMAT($sqlDateTo, '%Y-%m-01'), INTERVAL 12 MONTH)";
                break;
            case 'weekday':
                $sqlSelectField = "Date_Format(ordertime, '%Y-%m-%d')";
                $sqlGroupBy = 'WEEKDAY(ordertime)';
                $sqlSelectName = 'date';
                break;
            case 'week':
                $sqlSelectField = 'DATE_SUB(DATE(ordertime), INTERVAL WEEKDAY(ordertime)-3 DAY)';
                $sqlSelectName = 'date';
                $sqlDateSub = '7 * 10 DAY';
                break;
            case 'daytime':
                $sqlSelectField = 'DATE_FORMAT(ordertime, \'1970-01-01 %H:00:00\')';
                $sqlSelectName = 'date';
                break;
            case 'country':
                $sqlSelectField = 'c.countryname';
                $sqlGroupBy = 'ob.countryID';
                break;
            default:
                break;
        }

        if (!isset($sqlDateFrom)) {
            $sqlDateFrom = "DATE_SUB($sqlDateTo, INTERVAL $sqlDateSub)";
        }
        if (!isset($sqlGroupBy)) {
            $sqlGroupBy = $sqlSelectField;
        }
        if (!empty($shops)) {
            //$sqlWhere .= 'AND o.subshopID IN (' . Shopware()->Db()->quote($shops) .') ';

            foreach ($shops as $shop) {
                $shop = (int)$shop["id"];
                $sqlSelect .= "SUM(IF(o.subshopID=$shop, $sqlAmount, 0)) as `amount$shop`, ";
            }
        }

        $sql = "
            SELECT
        		COUNT(*) as `count`,
        		SUM($sqlAmount) as `amount`,
        		Date_Format(ordertime, '%W') as displayDate,
                $sqlSelect
                $sqlSelectField as `$sqlSelectName`
            FROM `s_order` o

            LEFT JOIN s_premium_dispatch d
            ON o.dispatchID = d.id

            LEFT JOIN s_core_paymentmeans p
            ON o.paymentID = p.id

            JOIN s_order_billingaddress ob
            ON o.id = ob.orderID

            JOIN s_core_countries c
            ON ob.countryID = c.id

            WHERE o.status NOT IN (4, -1)

            AND o.ordertime <= ?
            AND o.ordertime >= ?

            $sqlWhere

            GROUP BY $sqlGroupBy
            ORDER BY $sqlSelectName
        ";

        $data = Shopware()->Db()->fetchAll($sql,array($toDate, $fromDate));

        foreach ($data as &$row) {
            $row['count'] = (int)$row['count'];
            $row['amount'] = (float)$row['amount'];
            $row['date'] = strtotime($row['date']);

            if (!empty($shops)) {
                foreach ($shops as $shop) {
                    $shop = (int)$shop["id"];
                    $row['amount' . $shop] = (float)$row['amount' . $shop];
                }
            }
        }

        $this->View()->success = true;
        $this->View()->data = $data;
    }

    /**
     * Get statistics for shop visitors
     */
    public function visitsAction()
    {
        $data = array();
        $sqlSelect = null;

        $fromDate = $this->getFromDate();
        $toDate = $this->getToDate();

        $start = intval($this->Request()->start ? $this->Request()->start : 0);
        $limit = intval($this->Request()->limit ? $this->Request()->limit : 25);
        $sort = $this->Request()->sort;

        if (empty($sort[0])) {
            $sort[0] = array("property" => "datum", "direction" => "DESC");
        }

        $sort = $sort[0];

        // sw-3321: Also take subshops page impressions and unique visits into account in order to have correct
        // values for the corresponding main-shops.
        $shops = $this->getShops();
        if (!empty($shops)) {
            foreach ($shops as $key => $shop) {
                if ($key == 0) $sqlSelect = ",\n";
                $shop = (int)$shop["id"];
                $sqlSelect .= "SUM(IF(IF(cs.main_id is null, cs.id, cs.main_id)=$shop, s.pageimpressions, 0)) as `impressions$shop`, ";
                $sqlSelect .= "SUM(IF(IF(cs.main_id is null, cs.id, cs.main_id)=$shop, s.uniquevisits, 0)) as `visits$shop` ";
                if ($key < count($shops) - 1) $sqlSelect .= ",\n";
            }
        }
        $sql = "
        SELECT datum,SUM(pageimpressions) AS totalImpressions, SUM(uniquevisits) AS totalVisits
        $sqlSelect
        FROM s_statistics_visitors s
        LEFT JOIN s_core_shops cs ON s.shopID = cs.id
        WHERE datum <= ?
        AND datum >= ?
        GROUP BY datum
        ORDER BY {$sort["property"]} {$sort["direction"]}
        ";

        $data = Shopware()->Db()->fetchAll($sql,array($toDate, $fromDate));

        $this->View()->total = count($data);

        $data = array_splice($data, $start, $limit);
        foreach ($data as &$row) {
            $row['datum'] = strtotime($row['datum']);
        }
        $this->View()->success = true;
        $this->View()->data = $data;

    }

    /**
     * Get sales data for statistics
     * Kind of statistic is defined via
     * $this->Request()->getParam('type')
     *  Possible values:
     *  supplier,category,article,voucher
     * Returns json formatted result
     */
    public function orderDetailAnalyticsAction()
    {
        if (!$this->Request()->getParam('tax')) {
            $sqlAmount = 'od.price * od.quantity';
        } else {
            $sqlAmount = 'od.price / (100+tax) * 100) * od.quantity';
        }
        $sqlAmount = '(' . $sqlAmount . ')/currencyFactor';
        $sqlSelect = '';

        $fromDate = $this->getFromDate();
        $toDate = $this->getToDate();

        switch ($this->Request()->getParam('type')) {
            case 'supplier':
                $sqlSelectField = 's.name';
                $sqlGroupBy = 'a.supplierID';
                $sqlJoin = '
                    JOIN s_articles_supplier s
                    ON s.id = a.supplierID
                ';
                break;
            case 'category':
                $sqlSelectField = 'c.description';
                $sqlGroupBy = 'c.id';
                $node = $this->Request()->getParam('node', 'root');
                if ($node === 'root') {
                    $node = 1;
                } else {
                    $node = (int)$node;
                }
                $sqlSelect .= '(
                    SELECT parent FROM s_categories
                    WHERE c.id=parent LIMIT 1
                ) as `node`, ';
                $sqlJoin = "
                    JOIN s_categories c
                    ON c.parent=$node

                    JOIN s_categories c2
                    ON c2.active=1
                    AND c2.left >= c.left
                    AND c2.right <= c.right

                    JOIN s_articles_categories ac
                    ON ac.articleID=a.id
                    AND ac.categoryID=c2.id
                ";
                break;
            case 'article':
                $sqlSelectField = 'a.name';
                $sqlGroupBy = 'od.articleID';
                break;
            case 'voucher':
                break;
            default:
                break;
        }

        if (!isset($sqlGroupBy)) {
            $sqlGroupBy = $sqlSelectField;
        }

        $sql = "
            SELECT
        		COUNT(DISTINCT o.id) as `count`,
                SUM($sqlAmount) as `amount`,
                $sqlSelect
                $sqlSelectField as `name`
            FROM `s_order` o

            JOIN s_order_details od
            ON od.orderID = o.id AND od.modus=0

            JOIN s_articles a
            ON a.id = od.articleID

            $sqlJoin

            WHERE o.status NOT IN (4, -1)
            AND o.ordertime <= ?
            AND o.ordertime >= ?

            GROUP BY $sqlGroupBy

            ORDER BY `name`
        ";

        $data = Shopware()->Db()->fetchAll($sql,array($toDate, $fromDate));

        foreach ($data as &$row) {
            $row['count'] = (int)$row['count'];
            $row['amount'] = (float)$row['amount'];
        }

        $this->View()->success = true;
        $this->View()->data = $data;
    }

    /**
     * Get statistics for popular search terms
     * Possible sorting values: countRequests, searchterm, countResults
     * Sort is defined in array $this->Request()->sort (property=>...,direction=>ASC/DESC)
     * @return json formatted output
     */
    public function searchAnalyticsAction()
    {
        $data = array();
        $start = intval($this->Request()->start ? $this->Request()->start : 0);
        $limit = intval($this->Request()->limit ? $this->Request()->limit : 25);
        $sort = $this->Request()->sort;

        if (empty($sort[0])) {
            $sort[0] = array("property" => "countRequests", "direction" => "DESC");
        }

        $sort = $sort[0];

        $sql = "
        SELECT COUNT(searchterm) AS countRequests, searchterm,
        MAX(results) AS countResults FROM s_statistics_search GROUP BY searchterm
        ORDER BY {$sort["property"]} {$sort["direction"]}
        ";

        $data = Shopware()->Db()->fetchAll($sql);

        $this->View()->total = count($data);

        $data = array_splice($data, $start, $limit);

        $this->View()->success = true;
        $this->View()->data = $data;
    }

    /**
     * Get conversion rates
     * Basically number of orders for a day / number of visitors
     */
    public function conversionRateAction()
    {

        // Support subshops
        $shops = $this->getShops();
        $sqlSelect = "";
        $start = intval($this->Request()->start ? $this->Request()->start : 0);
        $limit = intval($this->Request()->limit ? $this->Request()->limit : 25);

        $fromDate = $this->getFromDate();
        $toDate = $this->getToDate();



        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $shop = (int)$shop["id"];
                $sqlSelect .= "\n 0 AS visits$shop, 0 AS orders$shop, 0 AS conversion$shop,\n";
            }
        }
        /**
         * Fetch total visitors and total orders for each day that may be occurs in statistics
         * Result - Sample:
         * Array ( [0] => Array
        (
        [date] => 2012-05-26
        [visitsTotal] => 2
        [visits1] => 2
        [orders1] => 0
        [conversion1] => 0
        [visits6] => 0
        [orders6] => 0
        [conversion6] => 0
        [visits9] => 0
        [orders9] => 0
        [conversion9] => 0
        [ordersTotal] => 0
        ) )
         */
        $sql = "
        	SELECT
        		datum as `date`,
        		SUM(s.uniquevisits) AS `totalVisits`,
        		$sqlSelect
        		(SELECT COUNT(DISTINCT id) FROM s_order WHERE s_order.status NOT IN (4,-1) AND DATE(s_order.ordertime) = datum) AS `totalOrders`
        	FROM
        		`s_statistics_visitors` AS s
        	WHERE datum <= ?
            AND datum >= ?
        	GROUP BY `date`
        	ORDER BY `date` DESC
       ";

        $result = Shopware()->Db()->fetchAll($sql,array($toDate, $fromDate));

        // Reformat result to use date as key
        $basicStats = array();
        foreach ($result as $row) {
            $row["totalConversion"] = round($row["totalOrders"] / $row["totalVisits"] * 100, 2);
            $basicStats[$row["date"]] = $row;
        }

        /**
         * If shop selection is active, get visitors and orders for each shop
         * Merge results into $basicStats Array
         */
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $shop = (int)$shop["id"];

                $sql = "
                SELECT datum AS `date`,
                uniquevisits AS visits
                FROM s_statistics_visitors WHERE shopID = ?
                ";

                $result = Shopware()->Db()->fetchAll($sql, array($shop));

                foreach ($result as $row) {
                    $basicStats[$row["date"]]["visits" . $shop] = $row["visits"];
                }

                $sql = "
                    SELECT
                        DATE(ordertime) as `date`,
                        COUNT(o.id) AS `orders`
                    FROM
                        `s_order` AS o
                    WHERE subshopID = ? AND status NOT IN (4,-1)
                    GROUP BY DATE(ordertime)
                    ORDER BY DATE(ordertime) DESC
               ";
                $result = Shopware()->Db()->fetchAll($sql, array($shop));
                if (!empty($result)) {
                    foreach ($result as $row) {
                        $basicStats[$row["date"]]["orders" . $shop] = $row["orders"];
                        if (!empty($basicStats[$row["date"]]["visits" . $shop])) {
                            $basicStats[$row["date"]]["conversion" . $shop] = round($row["orders"] / $basicStats[$row["date"]]["visits" . $shop] * 100, 2);
                        } else {
                            $basicStats[$row["date"]]["conversion" . $shop] = 0;
                        }
                    }
                }
            }
        }

        foreach ($basicStats as &$row) $row["date"] = strtotime($row["date"]);
        $this->View()->total = count($basicStats);
        $basicStats = array_splice($basicStats, $start, $limit);
        $this->View()->data = array_values($basicStats);
        $this->View()->success = true;

    }



    /**
     * helper to get all shops
     *
     * return shops
     */
    private function getShops(){
        $sql = '
            SELECT
              s.id , s.name,
              c.currency AS currency,
              c.name AS currencyName,
              c.templatechar AS currencyChar
            FROM s_core_shops s, s_core_currencies c
            WHERE s.currency_id = c.id AND s.main_id IS NULL
            ORDER BY s.default DESC, s.name
        ';
        return Shopware()->Db()->fetchAll($sql);

    }

    /**
     * helper to get the from date in the right format
     *
     * return DateTime | fromDate
     */
    private function getFromDate(){
        $fromDate = $this->Request()->getParam('fromDate');
        if (empty($fromDate)) {
            $fromDate = new \DateTime();
            $fromDate = $fromDate->sub(new DateInterval('P1M'));
        } else {
            $fromDate = new \DateTime($fromDate);
        }
        return $fromDate->format("Y-m-d H:i:s");
    }

    /**
     * helper to get the to date in the right format
     *
     * return DateTime | toDate
     */
    private function getToDate() {

        //if a to date passed, format it over the \DateTime object. Otherwise create a new date with today
        $toDate = $this->Request()->getParam('toDate');
        if (empty($toDate)) {
            $toDate = new \DateTime();
        } else {
            $toDate = new \DateTime($toDate);
        }
        //to get the right value cause 2012-02-02 is smaller than 2012-02-02 15:33:12
        $toDate = $toDate->add(new DateInterval('P1D'));
        $toDate = $toDate->sub(new DateInterval('PT1S'));
        return $toDate->format("Y-m-d H:i:s");
    }
}