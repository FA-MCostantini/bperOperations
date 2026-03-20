<?php

/**
 * This file is part of the Firstance BPER project.
 *
 * @copyright Firstance srl.
 */

/**
 * Trait TraitTryQuery.
 */
trait TraitTryQuery
{
    private ?PDO $connection = null;

    private array $stackQuery = [];
    private array $isLocked   = [];

    /**
     * @param string $fileName
     * @param string $destinationTable
     * @param array  $fields
     * @param string $separator
     * @param string $nullAs
     *
     * @throws Throwable
     */
    public function importFromFile(string $fileName, string $destinationTable, array $fields, string $separator = ';', string $nullAs = ''): void
    {
        if (0 == count($fields)) {
            throw new Exception('No fields specified');
        }

        $columnsList = trim(implode(',', $fields));
        $this->getConnect()->pgsqlCopyFromFile($destinationTable, $fileName, $separator, $nullAs, $columnsList);
    }

    /**
     * @param string $statement
     * @param array  $values
     * @param string $separator
     * @param string $nullAs
     *
     * @throws Throwable
     *
     * @return array
     */
    public function exportToArray(string $statement, array $values = [], string $separator = ';', string $nullAs = ''): array
    {
        $query = 'CREATE TEMP VIEW data.view_temp AS '.$statement;
        $this->tryQuery($query, $values);

        $stmt        = $this->tryQuery("SELECT column_name FROM information_schema.columns WHERE table_name = 'data.view_temp'");
        $columns     = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'column_name');
        $columnsList = trim(implode(',', $columns));

        $rows = $this->getConnect()->pgsqlCopyToArray('data.view_temp', $separator, $nullAs, $columnsList);

        $query = 'DROP VIEW data.view_temp';
        $this->tryQuery($query);

        return $rows;
    }

    /**
     * @param string $fileName
     * @param string $statement
     * @param array  $values
     * @param string $separator
     * @param string $nullAs
     *
     * @throws Throwable
     */
    public function exportToFile(string $fileName, string $statement, array $values = [], string $separator = ';', string $nullAs = ''): void
    {
        $createView = 'CREATE TEMP VIEW IF NOT EXISTS data.view_temp AS '.$statement;
        $getColumn  = "SELECT column_name FROM information_schema.columns WHERE table_name = 'data.view_temp'";
        $dropView   = 'DROP VIEW IF EXISTS data.view_temp';

        $this->tryQuery($dropView);
        $this->tryQuery($createView, $values);
        $stmt = $this->tryQuery($getColumn);

        $columnsList = trim(implode(',', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'column_name')));

        $this->getConnect()->pgsqlCopyToFile('data.view_temp', $fileName, $separator, $nullAs, $columnsList);

        $this->tryQuery($dropView);
    }

    /**
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if (!$this->getConnect()->inTransaction()) {
            return $this->getConnect()->beginTransaction();
        }

        return true;
    }

    private function setConnect(PDO $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return PDO
     */
    private function getConnect(): PDO
    {
        if (!$this->connection instanceof PDO) {
            $database = new Database();
            $this->setConnect($database->connect_db());
        }

        return $this->connection;
    }

    /**
     * @throws Throwable
     */
    private function vacuumAnalyze(string $table = ''): void
    {
        $this->tryQuery("VACUUM ANALYZE $table", [], false);
    }

    /**
     * @param string $stmt
     * @param array  $values
     * @param bool   $isSequence
     */
    private function addQueryInStack(string $stmt, array $values = [], bool $isSequence = false): void
    {
        $this->stackQuery[] = ['smtp' => $stmt, 'values' => $values, 'isSequence' => $isSequence];
    }

    /**
     * @param bool $transaction
     *
     * @throws Throwable
     */
    private function tryQueryStack(bool $transaction = true): void
    {
        $i = -1;
        try {
            if ($transaction && !$this->getConnect()->inTransaction()) {
                $this->getConnect()->beginTransaction();
            }
            foreach ($this->stackQuery as $i => $tryQuery) {
                if ($this->stackQuery[$i]['isSequence']) {
                    $stmt = $this->tryQuerySequences($tryQuery['smtp'], $tryQuery['values'], false);
                } else {
                    $stmt = $this->tryQuery($tryQuery['smtp'], $tryQuery['values'], false);
                }
                unset($stmt);
            }
            if ($transaction || $this->getConnect()->inTransaction()) {
                $this->getConnect()->commit();
            }
        } catch (Throwable $t) {
            if ($transaction || $this->getConnect()->inTransaction()) {
                $this->getConnect()->rollBack();
            }
            $this->debugPrintException($t, $this->stackQuery[$i]);
            throw new $t();
        } finally {
            $this->stackQuery = [];
        }
    }

    /**
     * @param string $stmt
     * @param array  $values
     * @param bool   $transaction
     *
     * @throws Throwable
     *
     * @return PDOStatement|null
     */
    private function tryQuery(string $stmt, array $values = [], bool $transaction = true): ?PDOStatement
    {
        try {
            if ($transaction && !$this->getConnect()->inTransaction()) {
                $this->getConnect()->beginTransaction();
            }
            switch (strtolower(trim($stmt))) {
                case 'commit':
                    $this->getConnect()->commit();
                    break;
                case 'rollback':
                    $this->getConnect()->rollBack();
                    break;
                default:
                    if (count($values) > 0) {
                        $statement = $this->getConnect()->prepare($stmt);
                        $statement->execute($values);
                    } else {
                        $statement = $this->getConnect()->query($stmt);
                    }
                    if ($transaction && $this->getConnect()->inTransaction()) {
                        $this->getConnect()->commit();
                    }

                    return $statement;
            }

            return null;
        } catch (Throwable $t) {
            if ($transaction && $this->getConnect()->inTransaction()) {
                $this->getConnect()->rollBack();
            }
            $this->debugPrintException($t, $stmt);
            throw $t;
        }
    }

    /**
     * @param string $stmt
     * @param array  $rows
     * @param bool   $transaction
     *
     * @throws Throwable
     *
     * @return int number af row(s) affected
     */
    private function tryQuerySequences(string $stmt, array $rows = [], bool $transaction = true): int
    {
        $rowCount = 0;
        try {
            if ($transaction || !$this->getConnect()->inTransaction()) {
                $this->getConnect()->beginTransaction();
            }
            $statement = $this->getConnect()->prepare($stmt);
            foreach ($rows as $row) {
                $statement->execute($row);
                $rowCount += $statement->rowCount();
            }
            unset($statement);
            if ($transaction || $this->getConnect()->inTransaction()) {
                $this->getConnect()->commit();
            }

            return $rowCount;
        } catch (Throwable $t) {
            if ($transaction || $this->getConnect()->inTransaction()) {
                $this->getConnect()->rollBack();
            }
            $this->debugPrintException($t, $stmt);
            throw $t;
        }
    }

    /**
     * Execute an insert statement like
     *  INSERT INTO myTable (a,b,c,...)
     *       VALUES (?, ?, ?, ...)
     *            , (?, ?, ?, ...)
     *            , (?, ?, ?, ...)
     *            ...
     *
     * @param string $tableName   myTable
     * @param string $colList     (a,b,c,...)
     * @param array  $rows        list values of any rows to insert
     * @param bool   $transaction
     *
     * @throws Throwable
     *
     * @return int
     */
    private function tryInsertSequence(string $tableName, string $colList, array $rows = [], bool $transaction = true): int
    {
        $HARD_LIMIT    = 65535;
        $rowCount      = 0;
        $preparedQuery = $stmt = "INSERT INTO $tableName ( $colList ) ";
        try {
            if ($transaction || !$this->getConnect()->inTransaction()) {
                $this->getConnect()->beginTransaction();
            }
            $i     = 0;
            $slice = $rows;
            while (count($slice)) {
                $paramUsed      = 0;
                $insert_values  = [];
                $question_marks = [];
                $preparedQuery  = $stmt;
                foreach ($slice as $row) {
                    if (($paramUsed += count($row)) > $HARD_LIMIT) {
                        break;
                    }
                    ++$i;
                    $question_marks[] = '('.$this->placeholders('?', count($row)).')';
                    array_push($insert_values, ...array_values($row));
                }
                $preparedQuery .= ' VALUES '.implode(',', $question_marks);
                $statement = $this->getConnect()->prepare($preparedQuery);
                $statement->execute($insert_values);
                $rowCount += $statement->rowCount();
                unset($statement);
                $slice = array_slice($rows, $i);
            }

            if ($transaction || $this->getConnect()->inTransaction()) {
                $this->getConnect()->commit();
            }

            return $rowCount;
        } catch (Throwable $t) {
            if ($transaction || $this->getConnect()->inTransaction()) {
                $this->getConnect()->rollBack();
            }
            $this->debugPrintException($t, $preparedQuery);
            throw $t;
        }
    }

    /**
     * @param string $text
     * @param int    $count
     * @param string $separator
     *
     * @return string
     */
    private static function placeholders(string $text, int $count = 0, string $separator = ','): string
    {
        $result = ($count > 0) ? array_fill(0, $count, $text) : [];

        return implode($separator, $result);
    }

    /**
     * @param string               $prefix
     * @param array<int, mixed>    $values
     * @param array<string, mixed> $bindArray
     *
     * @return string
     */
    private static function bindParamArray(string $prefix, array $values, array &$bindArray): string
    {
        $str = '';
        foreach ($values as $index => $value) {
            $str .= ':'.$prefix.$index.',';
            $bindArray[$prefix.$index] = $value;
        }

        return rtrim($str, ',');
    }

    /**
     * @param PDOStatement $statement $mode PDO::FETCH_* constants
     * @param int          $mode
     *
     * @return mixed
     */
    private function getQueryRecord(PDOStatement $statement, int $mode = PDO::FETCH_ASSOC): mixed
    {
        return $statement->fetch($mode);
    }

    /**
     * @param PDOStatement $statement $mode PDO::FETCH_* constants
     * @param int          $mode
     *
     * @return bool|array false on failure
     */
    private function getQueryRecords(PDOStatement $statement, int $mode = PDO::FETCH_ASSOC): array|false
    {
        return $statement->fetchAll($mode);
    }

    /**
     * @param PDOStatement $statement
     *
     * @return int Returns the number of rows affected by the last SQL statement
     */
    private function getQueryAffectedRows(PDOStatement $statement): int
    {
        return $statement->rowCount();
    }

    private function debugPrintException(Throwable $t, string $stmt): void
    {
        if (ENV_IS_DEV) {
            echo '<pre>'.$t->getMessage()."\n\n".$t->getTraceAsString()."\n\n".$stmt.'</pre>';
        }
    }
}
