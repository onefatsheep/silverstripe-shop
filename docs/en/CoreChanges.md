# Core Changes

Here are the subclasses, and extensions of standard Sapphire components for the shop module.

 * EcommerceCurrency - provides ability to customise currency formatting.
 * ShopPayment - provides some additional functionality that should eventually move to Payment itself.
 * ProductBulkLoader - extends CSVBulkLoader to provide shop-specific loading. See [Bulk Loading](BulkLoading) for more.

 * ShopDevelopmentAdminDecorator - extends DevelopmentAdmin so that we can call mysite/dev/shop


 * OptionalConfirmedPasswordField - requires entering a password twice to ensure it is correct.
 
 * I18nDatetime