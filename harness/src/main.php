<?php
namespace harness;
use Exception;

require_once __DIR__ . '/includes.php';

// Dus, gezien vanuit bookmark omdat we nog linken naar /core.js en /core.css
define('HARNESS_DIR', dirname(__DIR__));
define("HARNESS_ROUTER", substr(__DIR__ . '/router.php', strlen(HARNESS_DIR)+1));


function start_webserver ($path = '.', $opts = []) {
    $path = realpath($path);
    

    mkdirr('/tmp/harness');

    $toolPath = $opts['tool'] ?? $path;
    $package = read_json($toolPath . '/package.json') ?? [];

    $bookmarks_processes = [];
    if (file_exists("/tmp/harness/processes.json")) { 
        $bookmarks_processes = read_json("/tmp/harness/processes.json");

        foreach ($bookmarks_processes as $index => $p) {

            if (($p['args'] ?? []) === [$path, $opts]) {
                if (file_exists("/proc/" . $p['pid'])) {
                    $port = $p['port'];
                    $url = "http://localhost:$port";
                    system("firefox $url/#/ ");
                    echo "already running at $url\n";
                    return;
                } else {
                    unset($bookmarks_processes[$index]);
                }
            }
        }
    }
    $port = $opts['port'] ?? $package['harness']['port'] ?? rand(31000,32000);

    if ($opts['no-browser'] ?? false) {
        $openBrowser = fn() => '';
    } else {
        $browserOpts = isset($opts['new-window']) ? '--new-window ' : '';
        $openBrowser = fn() => system("firefox $browserOpts http://localhost:$port/#/ ");
    }

    $bookmarks_processes[] = [
        'pid' => getmypid(),
        'args' => [$path, $opts],
        'created_at' => date('Y-m-d H:i:s'),
        'port' => $port
    ];
    
    write_json('/tmp/harness/processes.json', array_values($bookmarks_processes));

    if (file_exists($path . '/start-harness.php')) {
        $openBrowser();
        define('HARNESS_PORT', $port);
        include($path . '/start-harness.php');
    } else {
        $openBrowser();

        // Ik wil die accepted / closing log berichten kwijt.
        // maar $pipes = " | grep -v " werkt niet...
        $pipes = '';
        $env = '';

        if ($opts['docker'] ?? false) {
            $opts['port'] = $port;
            $opts['pipes'] = $pipes;
            $opts['env'] = $env;

            _start_dockerized($path, $opts);
            
        } else {
            if ($opts['tool'] ?? false) {
                $env = "TOOL_DIR='{$opts['tool']}'";
            }    
            system("cd $path; $env php -d variables_order=EGPCS -S localhost:$port " . __DIR__ . "/router.php $pipes");
        }        
    }
}
function _start_dockerized($path, $opts) {
    $port = $opts['port'];
    $env = $opts['env'] ?? '';
    $pipes = $opts['pipes'] ?? '';
    
    // extend env
    $env = "HARNESS_INSIDE_DOCKER=1 $env";
    $env = "HARNESS_ORIGINAL_PATH='$path' $env";

    $HARNESS_DIR = HARNESS_DIR;
    $HARNESS_ROUTER = HARNESS_ROUTER;

    if (!function_exists('yaml_parse')) {
        throw new Exception('To use docker, please install php extension yaml');
    }
    $config = yaml_parse(`docker-compose config`);

    if (!($config && isset($config['services']))) { 
        throw new Exception('Docker service not found.');
    }
    $foundVolumes = [];
    foreach ($config['services'] as $serviceName => $service) {
        foreach (($service['volumes'] ?? []) as $v) {
            list($local, $remote) = explode(':', $v);
            if (strpos($path, $local) === 0) {
                $foundVolumes[] = [$serviceName, $local, $remote];
            }    
        }
    }

    if (is_string($opts['docker'])) {
        echo "it is a string";
        $foundVolumes = array_values(array_filter($foundVolumes, fn($v) => $v[0] === $opts['docker']));
        if (empty($foundVolumes)) {
            echo "You requested to start service " . $opts['docker'] . ", but there service does not include $path as volume.\n";;
            echo "Ending it here.\n";
            exit(1);
        }
    } elseif (empty($foundVolumes)) {
        echo "You requested to use docker, but there are no services that include $path as volume.\n";
        echo "Ending it here.\n";
        exit(1);
    }

    list($service, $localPath, $remotePath) = $foundVolumes[0];
    
    if (count($foundVolumes) > 1) {
        echo "The following docker services have $path as volume: " . join(' ', array_map(fn($x) => $x[0], $foundVolumes)) . "\n";
        echo "Using docker service $service\n";
    }
    
    $workingDirectory = str_replace($localPath, $remotePath, $path);
    // HARNESS_DEFAULT_HARNESS_PATH

    $harness = new Harness($path);
    $defaultHarnessPath = $harness->defaultHarnessPath;    
    $dockerArgs = [
        "--volume '" . HARNESS_DIR . "':'/opt/harness'",
    ];

    if ($defaultHarnessPath) {
        $dockerArgs[] = "--volume '$defaultHarnessPath':'/opt/default-harness'";
        $env = "HARNESS_DEFAULT_HARNESS_PATH='/opt/default-harness' $env";
    };
    if ($opts['tool'] ?? false) {
        $dockerArgs[] = "--volume '{$opts['tool']}':'/opt/tool'";
        $env = " TOOL_DIR='/opt/tool' $env ";
    } 
    $env = "cd $workingDirectory; $env";

    $START_ROUTER = "$env php -d variables_order=EGPCS -S 0.0.0.0:$port /opt/harness/$HARNESS_ROUTER $pipes";

    $dockerArgs = join(" \\\n", $dockerArgs);
    chdir($path);
    $command = "docker-compose run \
        $dockerArgs \
        -p 0.0.0.0:$port:$port \
        {$service} sh -c '$START_ROUTER' $pipes;
    ";

    echo "Running: $command\n\n\n";

    system($command);
}
if ($argv[1]) {
    switch ($argv[1]) {
        case 'build':
        case 'watch':
                    
        // Automagic building of bundle when you have request a bundle.js file.
        if (realpath('bundle.js')) { 
            $options = '';
            $command = 'build';
            if ($argv[1] == 'watch') {
                $command = 'watch';
                $options = '--no-hmr';
            }
            if (empty($cmd = command("ps aux | grep parcel | head -n -2 | grep " . escapeshellarg(realpath('bundle.js'))))) {
                system("parcel $command " . realpath('bundle.js') . " $options --no-source-maps &");
            } else {
                echo "There is already a bundler running..";
                print_r($cmd);
            }
        } else {
            echo "Could not find a bundle.js file here..";
        }

        break;
        case 'init':
            if ($argv[2]) {
                mkdirr($argv[2]);
                chdir($argv[2]);
            } 
            $object = new Harness(getcwd());
            $source = $object->defaultHarnessPath . '/default/template';
            if (is_dir($source)) { 
                system("rsync --ignore-existing -razv $source/ .");
                $package = read_json('package.json');
                $package['name'] = basename(getcwd());
                write_json('package.json', $package);
                echo "$argv[2] was initialized.";
            } else {
                echo "The default harness does not include a template for new projects.";
            }
        break;
        case 'exec':
        case 'run':

            echo getcwd();
            $object = new Harness(getcwd());
            $object->setErrorHandlers();
            $object->bootstrap();
            if (file_exists($argv[2])) {
                $controller = $object->loadController($argv[2]);
                $cname = get_class($controller);
                $method = $argv[3];
                $args = array_slice($argv, 4);
            } else {
                $default = $object->loadController('$default');

                if (method_exists($default, $argv[2])) {
                    $cname = get_class($default); 
                    $controller = $default;
                    $method = $argv[2];
                    $args = array_slice($argv, 3);
                } else {
                    $cname = $argv[2];
                    $method = $argv[3];
                    $args = array_slice($argv, 4);
                    $controller = $object->loadController($argv[2]);
                }
            }
            if (!$controller) {
                exit("Error: Controller not found: {$argv[2]}");
            }
            if (!is_object($controller)) {
                exit("Error: Controller is not an object: {$argv[2]}");
            }
            echo "# Call to $cname::$method\n";

            $result = call_user_func_array([$controller, $method], $args);
            if (is_iterable($result) && !is_array($result)) {
                foreach ($result as $r) {
                    echo json_encode($r, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
                }
            } else {
                echo json_encode($result, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
            }
            echo "\n";
        break;
        default:
            $path = $argv[1];
            $opts = parse_argv(array_slice($argv,2));
            start_webserver($path, $opts);
    }
}
