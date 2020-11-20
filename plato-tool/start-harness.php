<?php 
/**
 * @common 
 * Harness for stuff that should run inside a docker-compose network
 */

// @configurable
$path = '/home/joshua/Projects/Plato/platomania-webmen';
$docker_service = 'php';
$CONTAINER_PWD = '/var/www';

// @endconfigurable

if (!isset($path)) {
    throw new Exception('Configure $path (which should contain the docker-compose.yml');
}
if (!isset($docker_service)) {
    throw new Exception('Configure the docker-service name to place this tool into.');
}
if (!defined('HARNESS_LIB')) {
    throw new Exception('HARNESS_LIB not defined, did you run this via the `harness` command?');
}

$HARNESS_LIB = HARNESS_LIB;

$TOOL_DIR = __DIR__;
$TOOL_NAME = basename(__DIR__);

$CONTAINER_PWD = $CONTAINER_PWD ?? trim(shell_exec("docker-compose  -f $path/docker-compose.yml run $docker_service pwd"));

$START_ROUTER = "cd $CONTAINER_PWD/$TOOL_NAME; php -d variables_order=EGPCS -S 0.0.0.0:$port /opt/harness/src/harness/router.php";

system(
    "docker-compose  -f $path/docker-compose.yml run \
        -v '$HARNESS_LIB':'/opt/harness' \
        -v '$TOOL_DIR':'$CONTAINER_PWD/$TOOL_NAME' \
        -p 127.0.0.1:$port:$port \
        $docker_service sh -c '$START_ROUTER'" 
);