# l2j-database-consolidation

PHP скрипт для слияния базы данных двух серверов в одну.


Это может быть полезно, если у Вас есть два сервера, и вы хотите их объединить в один, в результате: персонажи, предметы, кланы (перечисленный вами список) будут объедены.


Для работы скрипта необходимо:
1. БД должны быть от одной сборки, либо иметь одинаковую архитектуру.
2. PHP (делалось на версии 7.4).

В скрипте все подробно расписано и прокомментировано, однако сделаю краткий мануал.

Подключение к БД.
    Перейдите в файл db/connect.php, найдите массив $firstDBConfig и $twoDBConfig и укажите данные подключения.
    Эти базы будут слиты в одну, и весь результат будет находится в бд $twoDBConfig.
С подключением закончили.

Теперь указываем какие таблицы и колонки объединять!

>Откройте файл index.php, перейдите к массиву $objID, в нем указывайте первым значением массива название таблицы, вторым ID объекта, которые необходимо проверять на совпадение между БД.
    Потом перейдите к массиву $resive, в нем необходимо указать строчные данные, это подойдет для названий кланов, имени персонажей, логинов, первым параметром массива укажите название БД, вторым колонку. При нахождении совпадения, будет добавлен префикс. Если у вас логин аккаунта logan22, станет qq_logan22.
 

Как это всё запустить?
>Если у Вас PHP установлен на ПК, то рекомендую через консоль запустить скрипт, указав путь к файлу index.php.
    В противном случае, необходимо разместить скрипт на веб сервере, и открыть его в браузере.

***Внимание: проверяйте работоспособность не на ЛАЙФСЕРВЕРЕ, а на дампах БД.***
