(For PayU Ukraine and Russian ONLY)
-------
{Корень сайта}/wp-content/plugins/woocommerce/classes/gateways/


#Модуль для CMS Wordpress WooCommerce
======

( Ссылка для IPN указана в настройках модуля )

#Установка
-------------
1. Скопировать папку payu в папку {Корень сайта}/wp-content/plugins/woocommerce/classes/gateways/
2. Открыть в редакторе файл {Корень сайта}/wp-content/plugins/woocommerce/woocommerce.php
2.1 Найти кусок кода с подобным содержанием :
`
function core_gateways( $methods ) {
		$methods[] = 'WC_Gateway_BACS'; 
		..
`		
2.2 Добавить строку  `$methods[] = 'WC_Gateway_PayU';`
3. Зайти в админку сайта
3.1 Выбрать меню "woocommerce" -> "настройки"
3.2 Выбрать вкладку "платежные системы"
3.3 Выбрать редактирование настроек PayU
![Выбор настроек][0]
4. Настроить модуль, добавив необходимые данные, руководствуясь подсказками
![Настройки][1]
5 Включить метод PayU



[0]: https://raw.github.com/PayUUA/Wordpress_Woocommerce/master/choose_settings.png
[1]: https://raw.github.com/PayUUA/Wordpress_Woocommerce/master/settings.png