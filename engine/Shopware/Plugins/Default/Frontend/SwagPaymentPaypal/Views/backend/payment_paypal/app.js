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
 * @package    Shopware_Paypal
 * @subpackage Paypal
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Shopware Application - Config module
 *
 * todo@all: Documentation
 */
//{block name="backend/payment_paypal/application"}
Ext.define('Shopware.apps.PaymentPaypal', {

    extend: 'Enlight.app.SubApplication',

    bulkLoad: true,
    loadPath: '{url action=load}',

    params: {},

    controllers: [ 'Main' ],

    stores: [
        'main.List'
    ],
    models: [
        'main.List'
    ],
    views: [
        'main.Window', 'main.List'
    ],

    /**
     * This method will be called when all dependencies are solved and
     * all member controllers, models, views and stores are initialized.
     */
    launch: function() {
        var me = this;
        me.controller = me.getController('Main');
        return me.controller.mainWindow;
    }
});
//{/block}

