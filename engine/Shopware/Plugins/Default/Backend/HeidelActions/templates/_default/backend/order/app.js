//{extends file="[default]backend/order/app.js"}
//{block name="backend/order/application"}
//{namespace name=backend/order/application}
Ext.define('Shopware.apps.Order-HeidelActions', {
	
    /**
     * Defines an override applied to a class.
     * @string
     */
    override: 'Shopware.apps.Order',
 
    /**
     * List of classes that have to be loaded before instantiating this class.
     * @array
     */
 
    requires: [ 'Shopware.apps.Order' ],
 
    /**
     * Initializes the class override to provide additional functionality
     * like a new full page preview.
     *
     * @public
     * @return void
     */
    initComponent: function() {
        var me = this;
        me.callOverridden(arguments);
    },
 
    createBaseFormLeft:function () {
        var me = this;
        var container = me.callOverridden(arguments);
        container[0].items.push({
            name: 'shoeSize',
            fieldLabel: "{s name=base/shoe_size}Schuhgr��e{/s}",
            helpText: "{s name=base/shoe_size}Hilfetext f�r das Schugr��efeld{/s}",
            helpTitle:"{s name=base/shoe_size_help_title}Schuhgr��e Hilfe Titel{/s}"
        });
        return container;
    }
});
//{/block}


    views:[
        'main.Window',
        'detail.Window',
        'detail.Overview',
        'detail.Communication',
        'detail.Position',
        'detail.Document',
        'detail.Detail',
        'detail.Billing',
        'detail.Shipping',
        'detail.Debit',
        'detail.History',
        'detail.Configuration',
        'detail.Heidelpay', // Heidelpay
        'list.Filter',
        'list.List',
        'list.Navigation',
        'list.Statistic',
        'list.Position',
        'list.Document',
        'batch.Window',
        'batch.Mail',
        'batch.Form',
        'batch.List',
        'batch.Progress'
    ],


