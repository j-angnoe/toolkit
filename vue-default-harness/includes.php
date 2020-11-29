<?php

/**
 * Standard functions that have been taken from 
 * univas and have been around for a really really long time
 * Probably pre 2010 :-D
 */

// Taken from univas
if (!function_exists('pr')) {
    function pr($data = null, $return = false)
    {
        $str = '<pre>';
        $str .= htmlspecialchars(print_r($data, true));
        $str .= '</pre>';

        if ($return) {
            return $str;
        }

        echo $str;
    }
}

// Taken from univas
if (!function_exists('firstval')) {
    function firstval($a)
    {
        if (func_num_args() > 1) {
            $a = func_get_args();
        } else {
            $a = toa($a);
        }
        foreach ($a as $y) {
            if ("$y" > '') {
                return $y;
            }
        }
    }
}

if (!function_exists('dd')) {
    // Throw an exception with the data so it will popup.
    // @todo - Make this more sophisticated.
    function dd($data = null)
    {
        throw new Exception(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}


/** 
 * Taken from univas
 * Make sure argument is an array **/
if (!function_exists('to_array')) {
    function to_array($a)
    {
        if (is_object($a) && $a instanceof Traversable) {
            foreach ($a as $x => $y) $r = array();
            $r[$x] = $y;
            return $r;
        } else {
            return is_array($a) ? $a : ($a ? array($a) : array());
        }
    }
}

/** 
 * Taken from univas
 * alias for to_array 
 **/
if (!function_exists('toa')) {
    function toa($a)
    {
        return to_array($a);
    }
}

/** 
 * Taken from univas
 * Create a nested array multiple times
 * @param $data data to be supernested
 * @param $supernest an array of which keys need to used for nesting..
 *
 * @warning this will convert objects to arrays.
 * Example: supernest($users, array('function', 'gender')) gives you
 * array(
 * 	'Programmer' => array(
 *			'male' => array(
 *				0 => $programmer1,
 *				1 => $programmer2
 *			),
 *			'female' => array($programmer3, $programmer4),
 *		),
 *		'Sales Representative' => array(
 * 			'male' => array(), 
 *      'female'=>array($sales1)
 *      )
 *	)
 **/
if (!function_exists('supernest')) {
    function supernest($data, $supernest)
    {
        $result = array();
        $supernest = toa($supernest);
        foreach ($data as $e) {
            $e = (array)$e;
            $ref = &$result;
            foreach ($supernest as $s) {
                $ref = &$ref[$e[$s]];
            }
            $ref[] = $e;
        }
        return $result;
    }
}

/** 
 * Inverse of supernest, this can convert a supernested array to 
 * a two-dimensional 
 **/
if (!function_exists('unsupernest')) {
    function unsupernest($data, $nest, $writeValue = true)
    {
        if (!$nest) {
            return $data;
        }
        $result = array();
        $field = array_shift($nest);
        $fn = __FUNCTION__;

        foreach ($data as $value => $rows) {
            //debug: pr ("$field = $value");
            foreach ($fn($rows, $nest) as $r) {
                if ($writeValue) $r[$field] = $value;
                $result[] = $r;
            }
        }
        return $result;
    }
}

/**
 * Nest an array on a certain value, this is the
 * 1-time supernest
 * @warning this will convert objects to arrays.
 **/
if (!function_exists('makeNested')) {
    function makeNested($data, $nestOn)
    {
        $result = array();
        foreach ($data as $value) {
            $value = (array)$value;
            if (!isset($result[$value[$nestOn]])) {
                $result[$value[$nestOn]] = array();
            }
            $result[$value[$nestOn]][] = $value;
        }

        return $result;
    }
}

/** 
 * Taken from univas
 * preg_grep based on keys, returns an array with keys and their values
 **/
if (!function_exists('preg_grep_keys')) {
    function preg_grep_keys($pattern, $input, $flags = 0)
    {
        $keys = preg_grep($pattern, array_keys($input), $flags);
        $vals = array();
        foreach ($keys as $key) {
            $vals[$key] = $input[$key];
        }
        return $vals;
    }
}

/** 
 * Taken from univas
 * amask: array mask some of the keys
 * Usage: amask($myarray, '*', '-id', '-name') // everthing except id and name
 * Usage: amask($myarray, 'id','name') => array('id'=>1, 'name'=>'joshua') // returns only a few keys..
 * $mask = array('*', '-id', '-name') return amask($myarray, $mask);
 **/
if (!function_exists('amask')) {
    function amask($x, $keys)
    {
        if (!is_array($keys)) {
            $args = func_get_args();
            $x = array_shift($args);
            $keys = $args;
            if (empty($keys) && is_string($keys)) {
                $keys = array_map('trim', explode(',', $keys));
            }
        }
        $joined = array();
        foreach ($keys as $k) {
            if (substr($k, 0, 1) === '-') {
                $k = substr($k, 1);
                if (strpos($k, '*') !== false) {
                    foreach (preg_grep('~^' . str_replace('*', '.*', $k) . '$~', array_keys($x)) as $_k) {
                        unset($joined[$_k]);
                    }
                }
                unset($joined[$k]);
                continue;
            } elseif ($k === '*') {
                foreach ($x as $xx => $yy) {
                    $joined[$xx] = $yy;
                }
                //$joined = am($joined, $x);//Geen AM want die re-indexeert numerieke shit.
                continue;
            } elseif (strpos($k, '*') !== false) {
                foreach (preg_grep('~^' . str_replace('*', '.*', $k) . '$~', array_keys($x)) as $_k) {
                    $joined[$_k] = @$x[$_k];
                }
                continue;
            }


            $joined[$k] = @$x[$k];
        }

        return $joined;
    }
}

/**
 * Returns the matched result
 * @usage get_preg_match($subject, $pattern)
 */
if (!function_exists('get_preg_match')) {
    function get_preg_match($subject, $pattern)
    {
        if (preg_match($pattern, $subject, $match)) {
            return $match;
        }
        return [];
    }
}

/** 
 * Deze matchAll is eigenlijk de meervoud van get_preg_match
 * Result:  [ get_preg_match()[0], get_preg_match[1] ]
 **/
if (!function_exists('get_preg_match_all')) {
    function get_preg_match_all($subject, $pattern)
    {
        if (preg_match_all($pattern, $subject, $matches)) {
            $result = array();
            foreach ($matches as $bx => $by) foreach ($by as $byx => $byy) $result[$byx][$bx] = $byy;
            return $result;
        }
        return array();
    }
}

/** ymdtotime = date(y-m-d, strtotime(x,y)) **/
if (!function_exists('ymdtotime')) {
    function ymdtotime($x, $y = null, $format = 'Y-m-d')
    {
        if (func_num_args() == 0) {
            return date($format);
        } elseif (func_num_args() == 1) {
            $x = is_numeric($x) ? $x : ($x !== '0000-00-00' ? strtotime($x) : false);
            return date($format, $x);
        } else {
            if ($y) {
                $y = is_numeric($y) ? $y : ($y !== '0000-00-00' ? strtotime($y) : false);
            }
            return date($format, strtotime($x, $y));
        }
    }
}

/** ymdtointerval('2012-01-01', '+1 week'); **/
if (!function_exists('ymdinterval')) {
    function ymdinterval($a, $b)
    {
        $a = is_numeric($a) ? $a : ($a !== '0000-00-00' ? strtotime($a) : false);
        $b = ymdtotime($b, $a);
        $r = array(date('Y-m-d', $a), $b);
        sort($r);
        return $r;
    }
}



if (!function_exists('findClosestFile')) { 
    /**
     * Super handy function to search for the closest
     * file given some path.
     * 
     * findClosestFile('package.json', '/path/to/my/project/app/some/folder')
     * might return /path/to/my/project/package.json
     */
    function findClosestFile($filename, $path = null) 
    {
        // paths from .git, package.json, composer.json

        $tryFiles = !is_array($filename) ? [$filename] : $filename;
        // print_R($tryFiles);

        $currentPath = realpath($path) ?: getcwd() . "/" . $path;

        while($currentPath > '/home' && $currentPath > '/') {
            // echo $currentPath . "\n";
            foreach ($tryFiles as $file) {
                // echo "$currentPath/$file\n";

                if (is_dir($currentPath . "/" . $file) || is_file($currentPath . "/" . $file)) {
                    return $currentPath . '/' . $file;
                }

            }    
            $currentPath = dirname($currentPath);
        }
        return false;
    }
}


if (!function_exists('command')) { 
    /**
     * Command: Run a shell command and return lines as an array.
     */
    function command($command) {
        return array_filter(explode("\n", trim(shell_exec($command))));
    }
}

if (!function_exists('read_json')) { 
    function read_json($file, $asObjects = false) {
        return json_decode(file_get_contents($file), $asObjects ? 0 : 1);
    }
}

if (!function_exists('write_json')) { 
    function write_json($file, $data) {
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
    }
}