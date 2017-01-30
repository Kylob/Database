<?php

namespace BootPress\Database;

class Component extends Driver
{
    protected $prepared = array();

    /**
     * Prepare and execute a query.
     * 
     * @param string|array $query  An SQL statement.
     * @param string|array $values The query parameters.
     * 
     * @return mixed Either ``false`` if there was a problem, or whatever the ``$db->execute()``d.
     *
     * ```php
     * $db->exec(array(
     *     'CREATE TABLE employees (',
     *     '  id INTEGER PRIMARY KEY,',
     *     '  name TEXT NOT NULL DEFAULT "",',
     *     '  title TEXT NOT NULL DEFAULT ""',
     *     ')',
     * ));
     * ```
     */
    public function exec($query, $values = array())
    {
        if ($stmt = $this->prepare($query)) {
            $result = $this->execute($stmt, $values);
            $this->close($stmt);
        }

        return (isset($result)) ? $result : false;
    }

    /**
     * Insert new records into a database table.
     * 
     * @param string|int $table Either the database table name, or the prepared statement id you got from calling this the first time.  You can also include *'INTO'* here which is handy for prepending a qualifier eg. *'OR IGNORE INTO table'*.
     * @param array      $data  Either the table column names (when preparing a statement), or a row of values (to insert).  If you are only inserting one record and want to save yourself some typing, then make this an ``array(name => value, ...)`` of columns.
     * @param string     $and   Anything you would like to add at the end of the query eg. *'ON DUPLICATE KEY UPDATE ...'*.
     * 
     * @return bool|int Either ``false`` if there was an error, a prepared ``$stmt`` to keep passing off as the **$table**, or the ``$id`` of the row you just inserted.  Don't forget to ``$db->close($stmt)``.
     *
     * ```php
     * if ($stmt = $db->insert('employees', array('id', 'name', 'title'))) {
     *     $db->insert($stmt, array(101, 'John Smith', 'CEO'));
     *     $db->insert($stmt, array(102, 'Raj Reddy', 'Sysadmin'));
     *     $db->insert($stmt, array(103, 'Jason Bourne', 'Developer'));
     *     $db->insert($stmt, array(104, 'Jane Smith', 'Sales Manager'));
     *     $db->insert($stmt, array(105, 'Rita Patel', 'DBA'));
     *     $db->close($stmt);
     * }
     * 
     * if ($db->insert('OR IGNORE INTO employees', array(
     *     'id' => 106,
     *     'name' => "Little Bobby'); DROP TABLE employees;--",
     *     'title' => 'Intern',
     * ))) {
     *     echo $db->log('count'); // 1 - It worked!
     * }
     * ```
     */
    public function insert($table, array $data, $and = '')
    {
        if (isset($this->prepared[$table])) {
            return $this->execute($table, $data);
        }
        $single = (count(array_filter(array_keys($data), 'is_string')) > 0) ? $data : false;
        if ($single) {
            $data = array_keys($data);
        }
        if (stripos($table, ' INTO ') !== false) { // eg. 'OR IGNORE INTO table'
            $query = "INSERT {$table} ";
        } else {
            $query = "INSERT INTO {$table} ";
        }
        $query .= '('.implode(', ', $data).') VALUES ('.implode(', ', array_fill(0, count($data), '?')).') '.$and;
        $stmt = $this->prepare($query);
        if ($single && $stmt) {
            $id = $this->insert($stmt, array_values($single));
            $this->close($stmt);

            return $id;
        }

        return $stmt;
    }

    /**
     * Modify records in a database table.
     * 
     * @param string|int $table Either the database table name, or the prepared statement id you got from calling this the first time.  You can also include *'SET'* here which is handy for getting some updates in that we can't otherwise do eg. *'table SET date = NOW(),'*.
     * @param array      $id    Either the name of the column with the unique identifier you will be referencing (when preparing a statement), or the unique identifier of the column you are updating.
     * @param array      $data  Either the table column names (when preparing a statement), or a row of values (to update).  If you are only updating one record and want to save yourself some typing, then make this an ``array(name => value, ...)`` of columns.
     * @param string     $and   Anything you would like to add at the end of the query after the WHERE eg. *'AND approved = "Y"'*.
     * 
     * @return bool|int Either ``false`` if there was an error, a prepared ``$stmt`` to keep passing off as the **$table**, or the ``$num`` of rows affected.  Don't forget to ``$db->close($stmt)``.
     *
     * ```php
     * if (!$db->update('employees SET id = 101', 'id', array(
     *     106 => array(
     *         'name' => 'Roberto Cratchit',
     *         'title' => 'CEO',
     *     )
     * ))) {
     *     echo $db->log('error'); // A unique id constraint
     * }
     * 
     * if ($stmt = $db->update('employees', 'id', array('title'))) {
     *     $db->update($stmt, 103, array('Janitor'));
     *     $db->update($stmt, 99, array('Quality Control'));
     *     $db->close($stmt);
     * }
     * ```
     */
    public function update($table, $id, array $data, $and = '')
    {
        if (isset($this->prepared[$table])) {
            $data[] = $id;

            return $this->execute($table, $data);
        }
        $first = each($data);
        $single = (is_array($first['value'])) ? $first['value'] : false;
        if ($single) {
            $data = array_keys($single);
        }
        if (stripos($table, ' SET ') !== false) { // eg. 'table SET date = NOW(),'
            $query = "UPDATE {$table} ";
        } else {
            $query = "UPDATE {$table} SET ";
        }
        $query .= implode(' = ?, ', $data).' = ? WHERE '.$id.' = ? '.$and;
        $stmt = $this->prepare($query);
        if ($single && $stmt) {
            $affected = $this->update($stmt, $first['key'], array_values($single));
            $this->close($stmt);

            return $affected;
        }

        return $stmt;
    }

    /**
     * Either update or insert records depending on whether they already exist or not.
     * 
     * @param string|int $table Either the database table name, or the prepared statement id you got from calling this the first time.  You cannot include *'SET'* or *'INTO'* here.
     * @param array      $id    Either the name of the column with the unique identifier you will be referencing (when preparing a statement), or the unique identifier of the column you are upserting.
     * @param array      $data  Either the table column names (when preparing a statement), or a row of values (to upsert).  If you are only upserting one record and want to save yourself some typing, then make this an ``array(name => value, ...)`` of columns.
     * 
     * @return bool|int Either ``false`` if there was an error, a prepared ``$stmt`` to keep passing off as the **$table**, or the ``$id`` of the row that was upserted.  Don't forget to ``$db->close($stmt)``.
     *
     * ```php
     * if ($stmt = $db->upsert('employees', 'id', array('name', 'title'))) {
     *     $db->upsert($stmt, 101, array('Roberto Cratchit', 'CEO'));
     *     $db->upsert($stmt, 106, array('John Smith', 'Developer'));
     *     $db->close($stmt);
     * }
     * 
     * $db->upsert('employees', 'id', array(
     *     107 => array(
     *         'name' => 'Ella Minnow Pea',
     *         'title' => 'Executive Assistant',
     *     ),
     * ));
     * ```
     */
    public function upsert($table, $id, array $data)
    {
        if (isset($this->prepared[$table]['ref']) && $this->execute($table, $id)) {
            $data[] = $id;
            if ($row = $this->fetch($table)) {
                return ($this->execute($this->prepared[$table]['ref']['update'], $data)) ? array_shift($row) : false;
            } else {
                return $this->execute($this->prepared[$table]['ref']['insert'], $data);
            }
        }
        $first = each($data);
        $single = (is_array($first['value'])) ? $first['value'] : false;
        if ($single) {
            $data = array_keys($single);
        }
        if ($stmt = $this->prepare("SELECT {$id} FROM {$table} WHERE {$id} = ?", 'row')) {
            $this->prepared[$stmt]['ref']['update'] = $this->update($table, $id, $data);
            $this->prepared[$stmt]['ref']['insert'] = $this->insert($table, array_merge($data, array($id)));
        }
        if ($single && $stmt) {
            $id = $this->upsert($stmt, $first['key'], array_values($single));
            $this->close($stmt);

            return $id;
        }

        return $stmt;
    }

    /**
     * Select data from the database.
     * 
     * @param string|array $select A SELECT statement.
     * @param string|array $values The query parameters.
     * @param string       $fetch  How you would like your row.
     * 
     * @return bool|int Either ``false`` if there was a problem, or a statement that you can ``$db->fetch($result)`` rows from.  Don't forget to ``$db->close($result)``.
     *
     * ```php
     * if ($result = $db->query('SELECT name, title FROM employees', '', 'assoc')) {
     *     while ($row = $db->fetch($result)) {
     *         print_r($row);
     *         // array('name'=>'Roberto Cratchit', 'title'=>'CEO')
     *         // array('name'=>'Raj Reddy', 'title'=>'Sysadmin')
     *         // array('name'=>'Jason Bourne', 'title'=>'Janitor')
     *         // array('name'=>'Jane Smith', 'title'=>'Sales Manager')
     *         // array('name'=>'Rita Patel', 'title'=>'DBA')
     *         // array('name'=>'John Smith', 'title'=>'Developer')
     *         // array('name'=>'Ella Minnow Pea', 'title'=>'Executive Assistant')
     *     }
     *     $db->close($result);
     * }
     * ```
     */
    public function query($select, $values = array(), $fetch = 'row')
    {
        if ($stmt = $this->prepare($select, $fetch)) {
            if ($this->prepared[$stmt]['type'] == 'SELECT' && $this->execute($stmt, $values)) {
                return $stmt;
            }
            $this->close($stmt);
        }

        return false;
    }

    /**
     * Get all of the selected rows from your query at once.
     * 
     * @param string|array $select A SELECT statement.
     * @param string|array $values The query parameters.
     * @param string       $fetch  How you would like your row.
     * 
     * @return array No false heads up here.  You either have rows, or you don't.
     *
     * ```php
     * foreach ($db->all('SELECT id, name, title FROM employees') as $row) {
     *     list($id, $name, $title) = $row;
     * }
     * ```
     */
    public function all($select, $values = array(), $fetch = 'row')
    {
        $rows = array();
        if ($stmt = $this->query($select, $values, $fetch)) {
            while ($row = $this->fetch($stmt)) {
                $rows[] = $row;
            }
            $this->close($stmt);
        }

        return $rows;
    }

    /**
     * Get all of the id's from your query, or whatever the first column you requested is.
     * 
     * @param string|array $select A SELECT statement.
     * @param string|array $values The query parameters.
     * 
     * @return bool|array Either ``false`` if there were no rows, or an ``array()`` of every rows first value.
     *
     * ```php
     * if ($ids = $db->ids('SELECT id FROM employees WHERE title = ?', 'Intern')) {
     *     // Then Little Bobby Tables isn't as good as we thought.
     * }
     * ```
     */
    public function ids($select, $values = array())
    {
        $ids = array();
        if ($stmt = $this->query($select, $values, 'row')) {
            while ($row = $this->fetch($stmt)) {
                $ids[] = (int) array_shift($row);
            }
            $this->close($stmt);
        }

        return (!empty($ids)) ? $ids : false;
    }

    /**
     * Get only the first row from your query.
     * 
     * @param string|array $select A SELECT statement.
     * @param string|array $values The query parameters.
     * @param string       $fetch  How you would like your row.
     * 
     * @return bool|array Either ``false`` if there was no row, or an ``array()`` of the first one fetched.
     *
     * ```php
     * if ($janitor = $db->row('SELECT id, name FROM employees WHERE title = ?', 'Janitor', 'assoc')) {
     *     // array('id'=>103, 'name'=>'Jason Bourne')
     * }
     * ```
     */
    public function row($select, $values = array(), $fetch = 'row')
    {
        if ($stmt = $this->query($select, $values, $fetch)) {
            $row = $this->fetch($stmt);
            $this->close($stmt);
        }

        return (isset($row) && !empty($row)) ? $row : false;
    }

    /**
     * Get only the first value of the first row from your query.
     * 
     * @param string|array $select A SELECT statement.
     * @param mixed        $values The query parameters.
     * 
     * @return bool|string Either ``false`` if there was no row, or the ``$value`` you are looking for.
     *
     * ```php
     * echo $db->value('SELECT COUNT(*) FROM employees'); // 7
     * ```
     */
    public function value($select, $values = array())
    {
        return ($row = $this->row($select, $values, 'row')) ? array_shift($row) : false;
    }

    /**
     * Prepare a query to be executed.
     * 
     * @param string|array $query An SQL statement.
     * @param string       $fetch How you would like the SELECT rows returned.  Either '**obj**', '**assoc**', '**named**', '**both**', or '**num**' (the default).
     * 
     * @return bool|int Either ``false`` if there was an error, or a ``$stmt`` id that can be``$db->execute()``d or ``$db->fetch()``ed.  Don't forget to ``$db->close($stmt)``.
     */
    public function prepare($query, $fetch = null)
    {
        $query = (is_array($query)) ? trim(implode("\n", $query)) : trim($query);
        $stmt = count(static::$logs[$this->id]) + 1;
        $start = microtime(true);
        $this->prepared[$stmt]['obj'] = $this->dbPrepare($query);
        static::$logs[$this->id][$stmt] = array(
            'sql' => $query,
            'count' => 0,
            'prepared' => microtime(true) - $start,
            'executed' => 0,
        );
        $this->prepared[$stmt]['params'] = substr_count($query, '?');
        $this->prepared[$stmt]['type'] = strtoupper(strtok($query, " \r\n\t"));
        if ($this->prepared[$stmt]['type'] == 'SELECT') {
            $this->prepared[$stmt]['style'] = $this->dbStyle(strtolower((string) $fetch));
        }
        if ($this->prepared[$stmt]['obj'] === false) {
            unset($this->prepared[$stmt]);
            if ($error = $this->dbPrepareError()) {
                static::$logs[$this->id][$stmt]['errors'][] = $error;
            }

            return false;
        }

        return $stmt;
    }

    /**
     * Execute a prepared statement.
     * 
     * @param int          $stmt   A ``$db->prepare(...)``d statement's return value.
     * @param string|array $values The query parameters.  If there is only one or none, then this can be a string.
     * 
     * @return mixed Either ``false`` if there was an error, ``true`` for a SELECT query, the inserted ``$id`` for an INSERT query, or the ``$num`` of affected rows for everything else.
     */
    public function execute($stmt, $values = null)
    {
        if (isset($this->prepared[$stmt])) {
            if (!is_array($values)) {
                $values = ($this->prepared[$stmt]['params'] == 1) ? array($values) : array();
            }
            $start = microtime(true);
            if ($this->dbExecute($this->prepared[$stmt]['obj'], array_values($values), $stmt)) {
                static::$logs[$this->id][$stmt]['executed'] += microtime(true) - $start;
                static::$logs[$this->id][$stmt]['count']++;
                switch ($this->prepared[$stmt]['type']) {
                    case 'SELECT':
                        return true;
                    break;
                    case 'INSERT':
                        return $this->dbInserted();
                    default:
                        return $this->dbAffected($this->prepared[$stmt]['obj']);
                }
            } elseif ($error = $this->dbExecuteError($this->prepared[$stmt]['obj'])) {
                static::$logs[$this->id][$stmt]['errors'][] = $error;
            }
        }

        return false;
    }

    /**
     * Get the next row from an executed SELECT statement.
     * 
     * @param int $stmt A ``$db->prepare(...)``d statement's return value.
     * 
     * @return mixed
     */
    public function fetch($stmt)
    {
        if (isset($this->prepared[$stmt]) && $this->prepared[$stmt]['type'] == 'SELECT') {
            return $this->dbFetch($this->prepared[$stmt]['obj'], $this->prepared[$stmt]['style'], $stmt);
        }

        return false;
    }

    /**
     * Closes a ``$db->prepared()``d statement to free up the database connection.
     * 
     * @param int $stmt A ``$db->prepare(...)``d statement's return value.
     */
    public function close($stmt)
    {
        if (isset($this->prepared[$stmt])) {
            if (isset($this->prepared[$stmt]['ref'])) {
                foreach ($this->prepared[$stmt]['ref'] as $value) {
                    $this->close($value);
                }
            }
            $this->dbClose($this->prepared[$stmt]['obj'], $stmt);
            unset($this->prepared[$stmt]);
        }
    }

    /**
     * Returns a **$query** with it's **$values** in place so that you can stare at it, and try to figure out what is going on.
     * 
     * @param string|array $query  An SQL statement.
     * @param mixed        $values The query parameters.
     * 
     * @return string
     */
    public function debug($query, $values = array())
    {
        $query = (is_array($query)) ? trim(implode("\n", $query)) : trim($query);
        if (!is_array($values)) {
            $values = (!empty($values)) ? array($values) : array();
        }
        foreach ($values as $string) {
            if (false !== $replace = strpos($query, '?')) {
                $query = substr_replace($query, $this->dbEscape($string), $replace, 1);
            }
        }

        return $query;
    }

    /**
     * Returns information about the previously executed query.
     * 
     * @param string $value If you don't want the whole array, then you can specify the specific value you do want.  Either '**sql**', '**count**', '**prepared**', '**executed**', '**errors**', '**average**', '**total**', or '**time**'.
     * 
     * @return mixed
     */
    public function log($value = null)
    {
        $log = (is_numeric($value)) ? static::$logs[$this->id][$value] : end(static::$logs[$this->id]);
        if (isset($log['errors'])) {
            $log['errors'] = array_count_values($log['errors']);
        }
        $log['average'] = ($log['count'] > 0) ? $log['executed'] / $log['count'] : 0;
        $log['total'] = $log['prepared'] + $log['executed'];
        $log['time'] = round($log['total'] * 1000).' ms';
        if ($log['count'] > 1) {
            $log['time'] .= ' (~'.round($log['average'] * 1000).' ea)';
        }
        if (is_null($value) || is_numeric($value)) {
            return $log;
        }

        return (isset($log[$value])) ? $log[$value] : null;
    }
}
