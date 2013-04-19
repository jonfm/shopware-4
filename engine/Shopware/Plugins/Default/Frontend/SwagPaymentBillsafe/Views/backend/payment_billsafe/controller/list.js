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
 * @subpackage Plugin
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * todo@all: Documentation
 */
Ext.define('PaymentBillsafe.controller.List', {
	
	extend: 'Ext.app.Controller',
	
	views: [
		'Viewport',
    	'List',
    	'Detail',
		'PayoutList',
		'ArticleList',
		'DetailData',
		'DetailPause',
		'DetailBook'
    ],
    
    models: [
    	'List',
    	'Status',
		'PayoutList',
		'ArticleList'
    ],
    
    stores: [
    	'List',
    	'Status',
		'PayoutList',
		'ArticleList',
		'ArticleType'
    ],
    
    init: function() {
    	this.listView = this.getListView().create({
			//statusStore: this.getStatusStore().load(),
			store: this.getListStore().load(),
			region: 'center'
		});
		
		this.detailView = this.getDetailView().create({
			payoutListView: this.getPayoutListView().create({
				store: this.getPayoutListStore()
			}),
			articleTypeStore: this.getArticleTypeStore(),
			articleListStore: this.getArticleListStore(),
			articleListView: this.getArticleListView(),
			detailDataView: this.getDetailDataView(),
			detailPauseView: this.getDetailPauseView(),
			detailBookView: this.getDetailBookView(),
			region: 'east',
			listView: this.listView
		});
    	
    	this.getView('Viewport').create({
    		items: [this.listView, this.detailView]
    	});
    	
    	this.listView.getSelectionModel().on('selectionchange', function(sm, records) {
            if (records.length) {
            	this.detailView.updateDetail(records[0]);
            }
        }, this);
    }
});