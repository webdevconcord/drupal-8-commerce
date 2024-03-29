# Модуль ConcordPay для Drupal 9 Commerce 2

Для работы модуля у вас должны быть установлены CMS **Drupal 8.8+** или **9+** и плагин электронной коммерции **Commerce 2.x**.

# Установка

1. Разархивировать папку с кодом модуля и скопировать в каталог *{YOUR_SITE}/modules* с сохранением структуры папок.
   
2. В административном разделе сайта зайти в подраздел *«Extend»*.

3. Активировать модуль **Commerce ConcordPay Payment** и нажать **«Install»**.

4. Перейти в раздел *«Commerce -> Конфигурация -> Payment gateways»* и нажать кнопку **Add payment gateway**.

5. ВАЖНО!
   - *Название платёжной системы* (**Name**): **ConcordPay Payment**;
   - *Идентификатор платёжной системы* (**Machine name**): **concordpay_payment**. 

6. Заполнить данные вашего продавца значениями, полученными от платёжной системы:
   - *Идентификатор продавца (Merchant ID)*;
   - *Секретный ключ (Merchant ID)*.

7. Сохранить настройки платёжного метода.

Модуль готов к работе.

*Модуль протестирован для работы с Drupal 8.9.11, Drupal 9.2.3, Commerce 8.x-2.24, PHP 7.4.*
