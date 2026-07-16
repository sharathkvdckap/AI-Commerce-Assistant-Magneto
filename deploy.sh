php bin/magento maintenance:enable
rm -rf var/cache/* var/page_cache/* var/di/* var/generation/* var/view_preprocessed/* generated/* pub/static/*
php -d memory_limit=12G bin/magento setup:upgrade
php -d memory_limit=12G bin/magento setup:di:compile
php -d memory_limit=12G bin/magento setup:static-content:deploy -f
php bin/magento c:f
php bin/magento c:c
chmod -R 0777 var/ generated/ pub/static
php bin/magento maintenance:disable
chmod -R 0777 var/ generated/ pub/