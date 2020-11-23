<?php
namespace harness;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function parse_argv($args = null) {
    global $argv;
    $argvString = join(' ', $args ?? array_slice($argv,1));
    $argvParser = new \samejack\PHP\ArgvParser();
    
    return $argvParser->parseConfigs($argvString);
}
/**
 * same as mkdir( , , recursive = true) but this
 * will not throw an exception if the directory exists.
 */
function mkdirr($pathname, $chmod = 0777) {
	if (is_dir($pathname)) {
		return true;
	}

	return mkdir($pathname, $chmod, true);
}

function command($command) {
	return array_filter(explode("\n", trim(shell_exec($command))));
}

function read_json($file, $asObjects = false) {
    return json_decode(file_get_contents($file), $asObjects ? 0 : 1);
}
function write_json($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
}

require_once __DIR__ . '/Harness.php';
require_once __DIR__ . '/HarnessServer.php';





