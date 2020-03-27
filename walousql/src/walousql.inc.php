<?php

class walousql
{
    private $path;
    private $table;
    private $table_path;
    private $tables = array();

    public function __construct($dataPath=__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR)
    {
        if (substr($dataPath, -1) != DIRECTORY_SEPARATOR)
        {
            $dataPath .= DIRECTORY_SEPARATOR;
        }
        if (!is_dir($dataPath))
        {
            trigger_error('new walousql($dataPath) : $dataPath="' . $dataPath . '" does not exist or is not a folder', E_USER_ERROR);
            return false;
        }
        if (!is_writable($dataPath))
        {
            trigger_error('new walousql($dataPath) : $dataPath="' . $dataPath . '" is not writable', E_USER_ERROR);
            return false;
        }
        else
        {
            $this->path = $dataPath;
            $this->setTable('default');
            return true;
        }
    }

    public function getDataPath()
    {
        return $this->path;
    }

    public function setTable($table, $force=false)
    {
        if(preg_match('`^[a-z0-9_\-\.]+$`i', $table))
        {
            $this->table = $table;
            $this->table_path = $this->path . $table . '.table.php';
            if (!isset($this->tables[$table]) || $force)
            {
                if (is_file($this->table_path))
                {
                    if (function_exists('opcache_invalidate') && strlen(ini_get('opcache.restrict_api')) < 1)
                    {
                        opcache_invalidate($this->table_path, true);
                    }
                    elseif (function_exists('apc_compile_file'))
                    {
                        apc_compile_file($this->table_path);
                    }
                    include $this->table_path;
                    $this->tables[$this->table] = $data;
                }
                else
                {
                    $this->tables[$this->table] = array();
                }
            }
            return true;
        }
        else
        {
            trigger_error('walousql->setTable($table) : $table="' . $table . '" is not a valid table name', E_USER_ERROR);
            return false;
        }
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getTablePath()
    {
        return $this->table_path;
    }

    public function set($rows, $erase=false)
    {
        if (count($rows) > 0)
        {
            foreach ($rows as $key => $row)
            {
                if (!isset($this->tables[$this->table][$key]))
                {
                    $erase = true;
                }
                elseif (!is_array($this->tables[$this->table][$key]))
                {
                    $erase = true;
                }
                if ($erase)
                {
                    $this->tables[$this->table][$key] = $row;
                }
                else
                {
                    foreach ($row as $new_key => $new_row)
                    {
                        $this->tables[$this->table][$key][$new_key] = $new_row;
                    }
                }
            }
            $this->writeTable();
        }
        return array_keys($rows);
    }

    public function add($rows)
    {
        $return_key = array();
        if (count($rows) > 0)
        {
            foreach ($rows as $row)
            {
                $this->tables[$this->table][] = $row;
                $array_keys = array_keys($this->tables[$this->table]);
                $return_key[] = end($array_keys);
            }
            $this->writeTable();
        }
        return $return_key;
    }

    public function selectAll($order=NULL)
    {
        $result = array_filter($this->tables[$this->table], function($value)
        {
            return $value !== NULL;
        });
        if ($order !== NULL)
        {
            $result = $this->array_msort($result, $order);
        }
        return $result;
    }

    public function selectByKey($key)
    {
        if (!isset($this->tables[$this->table][$key]))
        {
            return false;
        }
        elseif ($this->tables[$this->table][$key] === NULL)
        {
            return false;
        }
        else
        {
            return $this->tables[$this->table][$key];
        }
    }

    public function search($where=NULL, $order=NULL)
    {
        $result = array();
        if ($where !== NULL && !is_array($where))
        {
            $where = array($where);
        }
        foreach ($this->tables[$this->table] as $key => $row)
        {
            if ($where === NULL)
            {
                $ok = true;
            }
            else
            {
                $ok = false;
                $eval = '';
                foreach ($where as $current_where)
                {
                    if (is_array($current_where))
                    {
                        $current_where[1] = trim(strtolower($current_where[1]));
                        if (count($current_where) < 2)
                        {
                            trigger_error('walousql->search() : "' . var_export($current_where, true) . '" Condition must have at least two parameters', E_USER_ERROR);
                            return false;
                        }
                        elseif (in_array($current_where[1], array('null', '!null')) && count($current_where) != 2)
                        {
                            trigger_error('walousql->search() : "' . $current_where[1] . '" condition must have two parameters in "' . var_export($current_where, true) . '"', E_USER_ERROR);
                            return false;
                        }
                        elseif (in_array($current_where[1], array('=', '==', '===', '!=', '!==', '<', '<=', '>', '>=', 'like', 'like binary', '!like', '!like binary', 'start', 'start binary', 'end', 'end binary', 'in', '!in')) && count($current_where) != 3)
                        {
                            trigger_error('walousql->search() : "' . $current_where[1] . '" condition must have three parameters in "' . var_export($current_where, true) . '"', E_USER_ERROR);
                            return false;
                        }
                        elseif (in_array($current_where[1], array('in', '!in')) && count($current_where) === 3 && !is_array($current_where[2]))
                        {
                            trigger_error('walousql->search() : "' . $current_where[1] . '" third parameter must be an array in "' . var_export($current_where, true) . '"', E_USER_ERROR);
                            return false;
                        }
                        elseif (in_array($current_where[1], array('between', '!between')) && count($current_where) != 4)
                        {
                            trigger_error('walousql->search() : "' . $current_where[1] . '" condition must have four parameters in "' . var_export($current_where, true) . '"', E_USER_ERROR);
                            return false;
                        }
                        elseif ($row !== NULL)
                        {
                            switch ($current_where[1])
                            {
                                case '=' :
                                    $current_where[1] = '==';
                                case '==' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] == $current_where[2]);
                                    }
                                    break;

                                case '!=' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] != $current_where[2]);
                                    }
                                    break;

                                case '<' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] < $current_where[2]);
                                    }
                                    break;

                                case '<=' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] <= $current_where[2]);
                                    }
                                    break;

                                case '>' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] > $current_where[2]);
                                    }
                                    break;

                                case '>=' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] >= $current_where[2]);
                                    }
                                    break;

                                case '===' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] === $current_where[2]);
                                    }
                                    break;

                                case '!==' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] !== $current_where[2]);
                                    }
                                    break;

                                case 'like' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strpos(strtolower($row[$current_where[0]]), strtolower($current_where[2])) !== false);
                                    }
                                    break;

                                case 'like binary' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strpos($row[$current_where[0]], $current_where[2]) !== false);
                                    }
                                    break;

                                case '!like' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strpos(strtolower($row[$current_where[0]]), strtolower($current_where[2])) === false);
                                    }
                                    break;

                                case '!like binary' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strpos($row[$current_where[0]], $current_where[2]) === false);
                                    }
                                    break;

                                case 'start' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strpos(strtolower($row[$current_where[0]]), strtolower($current_where[2])) === 0);
                                    }
                                    break;

                                case 'start binary' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strpos($row[$current_where[0]], $current_where[2]) === 0);
                                    }
                                    break;

                                case 'end' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strrpos(strtolower($row[$current_where[0]]), strtolower($current_where[2])) === strlen($row[$current_where[0]]) - strlen($current_where[2]));
                                    }
                                    break;

                                case 'end binary' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = (strrpos($row[$current_where[0]], $current_where[2]) === strlen($row[$current_where[0]]) - strlen($current_where[2]));
                                    }
                                    break;

                                case 'null' :
                                    $ok = !isset($row[$current_where[0]]);
                                    break;

                                case '!null' :
                                    $ok = isset($row[$current_where[0]]);
                                    break;

                                case 'between' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] >= $current_where[2] && $row[$current_where[0]] <= $current_where[3]);
                                    }
                                    break;

                                case '!between' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = ($row[$current_where[0]] < $current_where[2] || $row[$current_where[0]] > $current_where[3]);
                                    }
                                    break;

                                case 'in' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = in_array($row[$current_where[0]], $current_where[2]);
                                    }
                                    break;

                                case '!in' :
                                    if (isset($row[$current_where[0]]))
                                    {
                                        $ok = !in_array($row[$current_where[0]], $current_where[2]);
                                    }
                                    break;

                                default :
                                    trigger_error('walousql->search() : Unknown condition : "' . $current_where[1] . '" in "' . var_export($current_where, true) . '"', E_USER_ERROR);
                                    return false;
                            }
                            $eval .= var_export($ok, true);
                        }
                    }
                    elseif (is_string($current_where))
                    {
                        $current_where = trim(strtolower($current_where));
                        switch ($current_where)
                        {
                            case 'and' :
                                $current_where = '&&';
                                break;

                            case 'or' :
                                $current_where = '||';
                                break;
                        }

                        $eval .= ' ' . $current_where . ' ';
                    }
                }
                try
                {
                    eval('$ok = ' . $eval . ';');
                }
                catch (Exception $e)
                {
                    trigger_error('walousql->search() : Global condition cannot be "' . $eval . '"', E_USER_ERROR);
                    return false;
                }
            }
            if ($ok)
            {
                $result[$key] = $row;
            }
        }
        if ($order !== NULL)
        {
            $result = $this->array_msort($result, $order);
        }
        return $result;
    }

    public function filter($callback, $flag=1, $order=NULL)
    {
        $result = array_filter($this->tables[$this->table], $callback, $flag);
        $result = array_filter($result, function($value)
        {
            return $value !== NULL;
        });
        if ($order !== NULL)
        {
            $result = $this->array_msort($result, $order);
        }
        return $result;
    }

    public function deleteByKey($key)
    {
        if (!is_array($key))
        {
            $key = array($key);
        }
        $result = false;
        if (count($key) > 0)
        {
            foreach ($key as $current_key)
            {
                if (isset($this->tables[$this->table][$current_key]))
                {
                    $result = true;
                    if (is_int($current_key))
                    {
                        $this->tables[$this->table][$current_key] = NULL;
                    }
                    else
                    {
                        unset($this->tables[$this->table][$current_key]);
                    }
                }
            }
            $this->writeTable();
        }
        return $result;
    }

    public function deleteAll($destroyTable=false)
    {
        if ($destroyTable)
        {
            $result = (count($this->tables[$this->table]) !== 0);
            $this->tables[$this->table] = array();
            @unlink($this->table_path);
            return $result;
        }
        else{
            return $this->deleteByKey(array_keys($this->tables[$this->table]));
        }
    }

    private function writeTable()
    {
        $last_keyless = false;
        $array_keys = array_keys($this->tables[$this->table]);
        rsort($array_keys);
        foreach ($array_keys as $key)
        {
            if (is_int($key))
            {
                $last_keyless = $key;
                break;
            }
        }

        foreach ($this->tables[$this->table] as $key => $row)
        {
            if ($row === NULL && is_int($key) && $key !== $last_keyless)
            {
                unset($this->tables[$this->table][$key]);
            }
        }

        if (count($this->tables[$this->table]) === 0)
        {
            @unlink($this->table_path);
        }
        elseif (function_exists('file_put_contents'))
        {
            file_put_contents($this->table_path, '<?php'."\n".'$data = ' . var_export($this->tables[$this->table], true) . ';'."\n".'?>');
        }
        else
        {
            $fp = fopen($this->table_path, 'w');
            fwrite($fp, '<?php'."\n".'$data = ' . var_export($this->tables[$this->table], true) . ';'."\n".'?>', true);
            fclose($fp);
        }
    }

    private function array_msort($array, $cols)
    {
        $colarr = array();
        foreach ($cols as $col => $order)
        {
            $colarr[$col] = array();
            foreach ($array as $k => $row)
            {
                $colarr[$col]['_'.$k] = strtolower($row[$col]);
            }
        }
        $eval = 'array_multisort(';
        foreach ($cols as $col => $order)
        {
            $eval .= '$colarr[\''.$col.'\'],'.$order.',';
        }
        $eval = substr($eval,0,-1).');';
        eval($eval);
        $ret = array();
        foreach ($colarr as $col => $arr)
        {
            foreach ($arr as $k => $v)
            {
                $k = substr($k,1);
                if (!isset($ret[$k])) $ret[$k] = $array[$k];
                $ret[$k][$col] = $array[$k][$col];
            }
        }
        return $ret;
    }

}

?>