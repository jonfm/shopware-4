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
 * @package    Shopware_Components_Model
 * @subpackage Model
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

namespace Shopware\Components\Model;

use Doctrine\ORM\Query\Expr,
    Doctrine\ORM\QueryBuilder as BaseQueryBuilder;

/**
 * The Shopware QueryBuilder is an extension of the standard Doctrine QueryBuilder.
 */
class QueryBuilder extends BaseQueryBuilder
{
    /**
     * @var string
     */
    protected $alias;

    /**
     * @param string $alias
     * @return QueryBuilder
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * Adds filters to the query results.
     *
     * <code>
     *      $this->addFilter(array(
     *          'name' => 'A%'
     *      ));
     * </code>
     *
     * <code>
     *      $this->addFilter(array(array(
     *          'property' => 'name'
     *          'value' => 'A%'
     *      )));
     * </code>
     *
     * <code>
     *      $this->addFilter(array(array(
     *          'property'   => 'number'
     *          'expression' => '>',
     *          'value'      => '500'
     *      )));
     * </code>
     *
     * @param array $filter
     * @return \Shopware\Components\Model\QueryBuilder
     */
    public function addFilter(array $filter)
    {
        $i = 0;
        foreach ($filter as $exprKey => $where) {
            if (is_object($where)) {
                $this->andWhere($where);
                continue;
            }

            $operator = null;
            $expression = null;

            if (is_array($where) && isset($where['property'])) {
                $exprKey = $where['property'];
                $expression = isset($where['expression']) ? $where['expression'] : null;
                $operator   = isset($where['operator'])   ? $where['operator']   : null;
                $where      = $where['value'];
            }

            if (!preg_match('#^[a-z][a-z0-9_.]+$#i', $exprKey)) {
                continue;
            }

            if (isset($this->alias) && strpos($exprKey, '.') === false) {
                $exprKey = $this->alias . '.' . $exprKey;
            }

            if (null == $expression) {
                switch (true) {
                    case is_string($where):
                        $expression = 'LIKE';
                        break;
                    case is_array($where):
                        $expression = 'IN';
                        break;
                    case is_null($where):
                        $expression = 'IS NULL';
                        break;
                    default:
                        $expression = '=';
                        break;
                }
            }

            $expression = new Expr\Comparison(
                $exprKey,
                $expression,
                $where !== null ? ('?' . $i) : null
            );


            if (isset($operator)) {
                $this->orWhere($expression);
            } else {
                $this->andWhere($expression);
            }

            if($where !== null) {
                $this->setParameter($i, $where);
                ++$i;
            }
        }
        return $this;
    }

    /**
     * Adds an ordering to the query results.
     *
     * <code>
     *      $this->addOrderBy(array(array(
     *          'property' => 'name'
     *          'direction' => 'DESC'
     *      )));
     * </code>
     *
     * @param string|array $sort The ordering expression.
     * @param string $order The ordering direction.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function addOrderBy($orderBy, $order = null)
    {
        /** @var $select \Doctrine\ORM\Query\Expr\Select */
        $select = $this->getDQLPart('select');
        if (is_array($orderBy)) {
            foreach ($orderBy as $order) {
                if (!isset($order['property']) || !preg_match('#^[a-zA-Z0-9_.]+$#', $order['property'])) {
                    continue;
                }

                if (isset($select[0]) && $select[0]->count() === 1
                        && isset($this->alias) && strpos($order['property'], '.') === false) {
                    $order['property'] = $this->alias . '.' . $order['property'];
                }

                if (isset($order['direction']) && $order['direction'] == 'DESC') {
                    $order['direction'] = 'DESC';
                } else {
                    $order['direction'] = 'ASC';
                }

                parent::addOrderBy(
                    $order['property'], $order['direction']
                );
            }
        } else {
            parent::addOrderBy($orderBy, $order);
        }
        return $this;
    }
}
