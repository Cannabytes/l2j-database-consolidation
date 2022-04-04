<?php
/**
 * Слияние баз
 * Внимание, данный скрипт сделан исключительно для импортирования
 * с одной БД в другую, которая ПОЛНОСТЬЮ структурно идентична.
 *
 * Проще говоря, у Вас сборка l2jFrozen, то данные нужно импортировать в l2jFrozen
 * Данные из таблицы сервера #1 будут импортированы в таблицу #2
 *
 * Внимание: Перед использованием, обязательно сделайте резервные копии БД.
 *
 * Во время написания использовал PHP 7.4 / MySQL 5.8
 *
 * 17.04.2021 - @Logan22
 * telegram - https://t.me/Cannabytes
 */

set_time_limit(60 * 15);
ini_set("memory_limit", "1G");
$startTime = new DateTime('now');

include "db/connect.php";
$connect = new connect();

/**
 * Внимание!
 * В массиве мы перечисляем объекты в таблицах, которые будут изменены перед
 * тем как попадут в основную БД. Это необходимо сделать, чтоб не было коллизий(дубликатов) ID объектов.
 *
 * 
 * Первым параметром массива укажите название таблицы, вторым параметром название колонки с ID, либо массив колонок с указанием ID
 */
$objID = [
    ["character_blocklist", ["Obj_Id", "target_Id"]],
    ["character_donate", "Obj_Id"],
    ["character_effects_save", "char_obj_id"],
    ["character_friends", "char_id"],
    ["character_friends", ["char_id", "friend_id"]],
    ["character_hennas", "char_obj_id"],
    ["character_macroses", ["char_obj_id", "id"]],
    ["character_quests", "char_id"],
    ["character_recipebook", "char_id"],
    ["character_shortcuts", "char_obj_id"],
    ["character_skills", "char_obj_id"],
    ["character_skills_save", "char_obj_id"],
    ["character_subclasses", "char_obj_id"],
    ["character_variables", "obj_id"],
    ["characters", "obj_Id"],
    ["clan_data", "leader_id"],
    ["clan_subpledges", "leader_id"],
    ["ally_data", "leader_id"],
    ["heroes", "char_id"],
    ["heroes_diary", "charId"],
    ["items", ["object_id", "owner_id"]],
    ["olympiad_nobles", "char_id"],
    ["pets", ["item_obj_id", "objId"]],
    ["raidboss_points", "owner_id"],
    ["seven_signs", "char_obj_id"],
    ["augmentations", "item_id"],
    ["bans", "obj_Id"],
    ["buffer_skillsave", "charId"],
    ["account_bonus", "account"],
    ["ally_data", ["ally_id", "leader_id"]],
];
$connect->changeObj($objID);
echo "Метода change object завершен : ".$startTime->diff(new DateTime('now'))->format('%S сек.') . "\n";

/**
 * Перечисляем таблицы и название колонки, которые будем проверять на совпадение между БД.
 * Если совпадение найдено, будем добавлять ПРЕФИКС в начале совпадающей строки.
 */
$resive = [
    ['accounts', 'login'],
    ['clan_data', 'clan_name'],
    ['characters', 'char_name'],
    ['characters', 'account_name'],
];
$connect->revise($resive);
echo "Метод revise завершен на: ".$startTime->diff(new DateTime('now'))->format('%S сек.'). "\n";;

/**
 * Список файлов, которые мы будем делать дампы.
 * Тут нужно указать название таблиц, которые мы будем импортировать
 * По умолчанию импортируем все что мы перечислили ранее, можем ещё какие-то добавить!
 */
$connect->createDump([
    $objID,
    $resive,
]);
echo "Метод createDump завершен : ".$startTime->diff(new DateTime('now'))->format('%S сек.'). "\n";;

//Начинаем импортировать в БД все изменения
$connect->import();
echo "\nСкрипт завершен за ".$startTime->diff(new DateTime('now'))->format('%S секунд'). "\n";;

