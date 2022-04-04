<?php

class connect
{

    private array $config = [
        //Префикс, который будет добавлен к аккаунтам дубликатам
        'prefix' => "qq_",
        //Кол-во итераций по сохранению запросов
		//Почему не миллион? Потому что размер запросов имеет ограничение
        'countSaveQuery' => 1000,
    ];

    private array $firstDBConfig = [
        'host' => '127.0.0.1',
        'db' => 'dbcopy1',
        'user' => 'root',
        'pass' => 'root',
    ];

    private array $twoDBConfig = [
        'host' => '127.0.0.1',
        'db' => 'dbcopy3',
        'user' => 'root',
        'pass' => 'root',
    ];

    //Из какой БД "достаем данные", хотя мы там для и некотоыре данные будем обновлять
    private PDO $firstDB;
    //В какую БД будем импортировать
    private PDO $twoDB;

    //Полный список файлов, которые мы дампим.
    //Данный список мы будем использовать чтоб импортировать файлы
    private array $dumps = [];

    function __construct() {
        $this->firstDB = $this->connect($this->firstDBConfig);
        $this->twoDB = $this->connect($this->twoDBConfig);
    }

    private function connect($readDBConfig) {
        list($host, $db, $user, $pass) = array_values($readDBConfig);
        $port = "3306";
        $charset = 'utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=$port";
        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    private function runRead($query, $args = []) {
        return $this->run($query, $args);
    }

    private function runWrite($query, $args = []) {
        return $this->run($query, $args);
    }

    private function run($query, $args = []) {
        $db = (debug_backtrace()[1]['function'] == 'runRead') ? $this->firstDB : $this->twoDB;
        try {
            if (!$args) {
                return $db->query($query);
            }
            $db = $db->prepare($query);
            $db->execute($args);
            return $db;
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    //Структура таблицы
    private function tableStruct($tablename) {
        $struct = "DESCRIBE $tablename;";
        $struct = $this->runRead($struct)->fetchAll();
        return $struct;
    }

    //Таблица с которой мы получим весь массив данных
    private function tableData($tablename): array {
        return $this->runRead("SELECT * FROM " . $tablename)->fetchAll();
    }

    /**
     */
    public function revise($resive) {
        foreach ($resive as $obj) {
            $table = $obj[0];
            $colums = $obj[1];
            $sql = "SELECT * FROM {$this->firstDBConfig['db']}.{$table} UNION ALL SELECT * FROM {$this->twoDBConfig['db']}.{$table};";
            $this->changeResive($table, $colums, $this->duplicatesNames($this->runRead($sql)->fetchAll(), $colums));
            echo $this->createPoint("Обновлена таблица {$table}");
        }
    }

    /**
     * Если найдены дубликаты, производим замену
     */
    private function changeResive($table, $colums, $arrDublicate) {
        if (!empty($arrDublicate)) foreach ($arrDublicate as $oldName => $dublicate) {
            $newChange = $dublicate[$colums];
            $sql = "UPDATE `{$table}` SET `{$colums}`='{$newChange}' WHERE `{$colums}`='{$oldName}';";
            try {
                $this->firstDB->query($sql);
            } catch (Exception $e) {
                echo "<br>ВОЗНИКЛА ОШИБКА<br>";
                echo "<pre>{$e}</pre>";
                echo "<hr>";
            }
        }
    }

    /**
     * После всех изменений, сделаем дамп таблицы
     */
    public function createDump($dumps) {
        $dumps = $this->dumperArray($dumps);
        foreach ($dumps as $table) {
            $dataTable = $this->tableData($table);
            $structTable = $this->tableStruct($table);
            $line = "";
            $params = "";
            $countQuery = 0;
            $countData = count($dataTable);
            $sqlInsert = "INSERT INTO `{$table}` (%s) VALUES\n%s";
            $countStructTable = count($structTable);
            $i = 0;
            $field = ''; // Структура запроса
            foreach ($structTable as $row) {
                $i++;
                $field .= "`{$row['Field']}`";
                if ($countStructTable != $i) {
                    $field .= ", ";
                }
            }
            foreach ($dataTable as $data) {
                $countData--;
                $countDub = count($data);
                $countQuery++;
                //Формируем параметры в запросе
                foreach ($data as $val => $args) {
                    if ($countDub == count($data)) {
                        $params .= " (";
                    }
                    $countDub--;
                    $argType = gettype($args);
                    if ($argType == 'NULL') {
                        $params .= 'null';
                    } elseif ($argType == 'integer') {
                        $params .= $args;
                    } else {
                        $params .= "'" . addslashes($args) . "'";
                    }
                    if ($countDub != 0) {
                        $params .= ", ";
                    }
                    if ($countDub == 0) {
                        if ($countData == 0) {
                            $params .= ");\n";
                            $countQuery = 0;
                        } else {
                            if ($countQuery == $this->config['countSaveQuery']) {
                                $params .= ");\n\n";
                            } else {
                                $params .= "),\n";
                            }
                        }
                    }
                    $line = "{$params}";
                }
                if ($countQuery == $this->config['countSaveQuery'] or $countData == 0) {
                    $s = sprintf($sqlInsert, $field, $line);
                    if (file_put_contents("{$table}.sql", $s, FILE_APPEND)) {
                        echo $this->createPoint("Сохранение в {$table}.sql");
                    }
                    $countQuery = 0;
                    $params = "";
                    $line = "";
                }
            }
        }
    }

    /**
     * Формируем список тех таблиц, которые будем дампить!
     * @param $var
     * @return array
     */
    private function dumperArray($var): array {
        $a = [];
        foreach ($var as $s)
            if (is_array($s)) {
                foreach ($s as $row) {
                    $a[] = $row[0];
                }
            } else {
                $a[] = $s;
            }
        $this->dumps = array_unique($a);
        return $this->dumps;
    }


    // Возвращаем дубликаты
    private function duplicatesNames($array, $name): array {
        $duplicates = [];
        $countDuplicates = 0;
        foreach ($array as $key => $item) {
            if (isset($ids[$item[$name]])) {
                $countDuplicates++;
                $oldName = $item[$name];
                $item[$name] = $this->nickRename($item[$name], $this->config['prefix']);
                $duplicates[$oldName] = $item;
            } else {
                $ids[$item[$name]] = true;
            }
        }
        return $duplicates;
    }


    /**
     * Если ник длинее или равен 16 символов, обрезаем (в начале) и вставляем префикс
     * @param $nickname
     * @param $prefix
     * @return mixed|string
     */
    private
    function nickRename($nickname, $prefix): string {
        $count = strlen($nickname);
        if ($count + strlen($prefix) - 16 >= 0) {
            $nickname = mb_substr($nickname, $count + strlen($prefix) - 16);
            $nickname = $prefix . $nickname;
        } else {
            $nickname = $prefix . $nickname;
        }
        return $nickname;
    }


    /**
     * Функция для смены всех ID объектов.
     *
     * @param $table - Таблица
     * @param $columns - массив объектов или строка которые изменим в БД
     */
    function changeObj($objID) {
        foreach ($objID as $obj) {
            $table = $obj[0];
            $columns = $obj[1];
            $columnLine = "";
            if (is_array($columns)) {
                $countColumns = count($columns);
                foreach ($columns as $column) {
                    $countColumns--;
                    if ($countColumns != 0) {
                        $columnLine .= "`{$column}`=CEIL(`{$column}`*1.616), ";
                    }
                    if ($countColumns == 0) {
                        $columnLine .= "`{$column}`=CEIL(`{$column}`*1.616);";
                    }
                }
            } else {
                $columnLine = "`{$columns}`=CEIL(`{$columns}`*1.616);";
            }
            $sql = "UPDATE `{$table}` SET $columnLine";
            $this->runRead($sql);
        }
    }

    /**
     * Теперь все файлы будем загружать в БД
     */
    public function import($arr = []) {
        if (empty($arr)) $dumpList = $this->dumps; else {
            $dumpList = $arr;
        }
        foreach ($dumpList as $dump) if (file_exists($dump . ".sql")) {
            $file = file_get_contents($dump . ".sql");
            $linesQuery = explode(");\n", $file, -1);
            $part = 0;
            $countLinesQuery = count($linesQuery);
            foreach ($linesQuery as $row) {
                $this->runWrite($row . ");");
                $part++;
                $percent = $this->percent($part, $countLinesQuery);
                echo $this->createPoint("Импортирование $dump {$percent}%");
            }
        }
		echo $this->createPoint("Импортирование в БД завершено");
    }

    /**
     * Выводим проценты
     * @param $current
     * @param $total
     * @return float
     */
    private function percent($current, $total): int {
        return  round($current * 100 / $total, 2);
    }

    private function createPoint($line) {
        $countPoint = 70;
        $countLine = mb_strlen($line);
        $countPoint -= $countLine;
        $point = "";
        for ($i = $countPoint; $i >= 0; $i--) {
            $point .= " ";
        }
        return sprintf("%s%s\r", $line, $point);
    }

}