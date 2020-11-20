<?php

// @inserts php-includes


// @endinserts

function command($command) {
	return array_filter(explode("\n", rtrim(shell_exec($command))));
}

if (!function_exists('dd')) { 
    function dd($x) { throw new Exception(print_r($x, true)); } 
}

if (!function_exists('read_json')) { 
    function read_json($file, $asObjects = false) {
        return json_decode(file_get_contents($file), $asObjects ? 0 : 1);
    }
}

function write_json($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
}

function firstval($array) {
    return $array[0] ?? null;
}

$GLOBALS['settings'] = $GLOBALS['settings'] ?? read_json('settings.json');
