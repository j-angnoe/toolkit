<?php



class BaseExecutor
{
    function __construct(&$context)
    {
        $this->context = &$context;
    }

    function getCode()
    {
        return $this->context['code'];
    }

    function setOutputter($output)
    {
        $this->context['meta']['output'] = $output;
    }

    /**
     * Set outputter if none has been set.
     */
    function setDefaultOutputter($output) {
        $this->context['meta']['output'] ??= $output;
    }

    function getCodeStripped()
    {
        $code = $this->getCode();

        // remove single line comments
        $code = preg_replace('~^\s*(#|//).+$~m', '', $code);
        // remove multi line comments
        $code = preg_replace('~/\*.+?\*/~s', '', $code);
        return trim($code);
    }
}
class DefaultExecutor extends BaseExecutor
{
    function __invoke()
    {
        $stripped = $this->getCodeStripped();

        if (preg_match('~^\s*select~i', $stripped)) {
            return (new SqlExecutor($this->context))();
        }
        return (new PhpExecutor($this->context))();
    }
}

class SqlExecutor extends BaseExecutor
{

    function __invoke()
    {
        require_once __DIR__ . '/lib/DB.php';

        // @todo - limits and stuff

        $this->setOutputter('table');

        return DB::fetchAll($this->getCodeStripped());
    }
}

class PhpExecutor extends BaseExecutor
{

    function runCode($code = null) { 
        $autoloader = findClosestFile('vendor/autoload.php');

        if ($autoloader) {
            require_once $autoloader;
        }

        $bootstrap = findClosestFile(['bootstrap.php','bootstrap/cli.php','bootstrap/app.php']);
        if ($bootstrap) {
            require_once $bootstrap;
        }

        $code = $code ?? $this->getCode();
        $lines = substr_count($code,"\n");
        $semicolons = substr_count($code,";");
        
        if ($lines === 0 || $semicolons === 1) {
            if (strpos($code,';') === false) {
                $code .= ';';
            }
            if (!preg_match('/^(return|echo)[( ].+/', $code)) {
                $code = 'return ' . $code;
            }
        }

        $code = $this->processPrintStatements($code);

        $code = 'return function () { ' . $code . PHP_EOL . ' };';
        $fn = eval($code);
        $data = $fn();

        if (!is_array($data) && is_iterable($data)) {
            $this->setDefaultOutputter('table');
            $data = iterator_to_array($data);
        }
        return $data;

    }

    function preg_extract($pattern, $subject) {
        $matches = [];
        $newContent = preg_replace_callback($pattern, function ($match) use (&$matches) {
            if (!$matches) { 
                $matches = $match;
                return '';
            } else {
                return $match[0];
            }
        }, $subject);

        return [$matches, $newContent];
    }

    function __invoke()
    {
        
        if (preg_match('~^class ~', $this->getCodeStripped())) {
            return (new PhpControllerExecutor($this->context))();
        }

        $code = $this->getCode();
        list($blockMatch, $code) = $this->preg_extract('~@input(.+?)[^\n]*@endinput~s', $code);

        if ($blockMatch) {
            if ($this->context['interactions'] === null) {
                echo '<form method="POST" action="/dev/null" style="white-space: initial;">' . $blockMatch[1] . '<div><input type="submit"></form>';
            } else {
                return $this->runCode($code);
            }
        } else {
            return $this->runCode($code);
        }

    }

    /**
     * Adds newlines to print statements.
     */
    function processPrintStatements($code) {
        $tokens = token_get_all('<?php ' . $code);
        $buffer = '';
        while(false !== ($tok = next($tokens))) {
            if (is_array($tok) && $tok[0] == T_PRINT) {
                $buffer .= 'print';
                while(false !== ($tok = next($tokens))) {
                    if ($tok === ';') {
                        $buffer .= ' . "\n";';
                        break;
                    }
                    $buffer .= is_array($tok) ? $tok[1] : $tok;
                }
            }
            $buffer .= is_array($tok) ? $tok[1] : $tok;
        }
        return $buffer;
    }
}


// use ReflectionClass;
// use ReflectionMethod;

// class PhpControllerExecutor extends BaseExecutor {
//   function __invoke() {
//     eval($this->getCode());

//     $lastClass = @end($a=get_declared_classes());

//     if ($this->context['interactions'] === null) {
//       $this->setOutputter('php-controller');

//       $this->context['meta']['signature'] = $this->getApi($lastClass);

//     }

//     if ($call = $this->context['interactions']['phpControllerCall'] ?? false) {
//       $object = new $lastClass;
//       $method = $call[0];
//       $args = $call[1];

//       return $object->$method(...$args);
//     }
//     return $this->context['interactions'];

//   }


//   private function getApi($instance) {
//     $refl = new ReflectionClass($instance);
//     $methods = [];


//     foreach ($refl->getMethods(ReflectionMethod::IS_PUBLIC) as $meth) {
//         $methodName = $meth->getName();
//         $methodArgs = [];
//         foreach ($meth->getParameters() as $param) {
//             $methodArgs[] = [
//                 'name' => $param->getName(),
//                 'position' => $param->getPosition(),
//                 'optional' => $param->isOptional()
//             ];
//         }
//         $methods[$methodName] = [
//             'name' => $methodName,
//             'description' => $this->stripDocComment($meth->getDocComment()),
//             'arguments' => $methodArgs
//         ];
//     }

//     return $methods;
//   }

//   private function stripDocComment($comment) {
//     return trim(preg_replace('/\n\s*\*\s*/', "\n", trim(trim($comment),'/*')));
//   }
// }

class exec
{

    function saveScript(&$context)
    {
        if ($context['meta']['dontrun'] ?? false) {
            $doExecute = false;
        }
        $context['debug']['annotations'] = $context['meta'];
        $context['debug']['dir'] = __DIR__;

        if (isset($context['meta']['id'])) {

            $filename = preg_replace('~-+~', '-', preg_replace('~[^a-z0-9-]~', '-', $context['meta']['id'])) . '/script.txt';

            $path = $_ENV['DEVTOOLS_EXEC_PATH'] . '/' . $filename;

            $dir = dirname($path);

            $context['debug']['filename'] = $filename;

            $now = date('Y-m-d H:i:s');

            $context['meta']['modified'] = $now;

            if (!is_dir($dir)) { 
                @mkdir($dir, 0777);
            }
            if (!is_dir($dir .'/runs')) { 
                @mkdir($dir . '/runs', 0777);
            }

            $context['code'] = $this->writeAnnotations($context['code'], $context['meta']);

            file_put_contents($path, $context['code']);
            @chmod($path, 0777);
            $context['callback']->add(function ($data) use ($dir, $now) {
                if ($data) {
                    file_put_contents("$dir/runs/$now.json", json_encode($data, JSON_PRETTY_PRINT));
                    @chmod("$dir/runs/$now.json", 0777);
                }
            });
        }
    }

    function execute($code, $interactions = null)
    {
        $context = [
            'debug' => [],
            'code' => $code,
            'meta' => $this->extractAnnotations($code),
            'callback' => function () {
            },
            'interactions' => $interactions
        ];

        $doExecute = true;

        $context['callback'] = new class
        {
            var $callbacks = [];

            function add($callback)
            {
                $this->callbacks[] = $callback;
            }

            function __invoke(...$args)
            {
                if (!empty($this->callbacks)) { 
                    array_map('call_user_func', $this->callbacks, $args);
                }
            }
        };

        if (is_string($code)) {
            $this->saveScript($context);
        }

        $executor = $this->getExecutor($context);

        if ($doExecute) {

            if ($interactions) {
                if ($interactions['GET'] ?? false) {
                    $restoreGET = $_GET;
                    $context['callback']->add(function () use ($restoreGET) {
                        $_GET = $restoreGET;
                    });
                    if (is_string($interactions['GET'])) {
                        parse_str(ltrim($interactions['GET'], '?'), $_GET);
                    } else {
                        $_GET = $interactions['GET'];
                    }
                }
                if ($interactions['POST'] ?? false) {
                    $restorePOST = $_POST;
                    $context['callback']->add(function () use ($restorePOST) {
                        $_POST = $restorePOST;
                    });
                    if (is_string($interactions['POST'])) {
                        parse_str(ltrim($interactions['POST'], '?'), $_POST);
                    } else {
                        $_POST = $interactions['POST'];
                    }
                }
            }

            ob_start();
            $data = $executor();
            $content = ob_get_clean();

            // if (is_object($data) && !is_iterable($data)) {
            //     $command = [
            //         'type' => 'object',
            //         'signature' => get_class_methods($data)
            //     ];
            //     $data = null;
            // }

            $context['callback']($data);
        } else {
            $data = null;
        }

        return array_merge($executor->context, [
            'data' => $data,
            'content' => $content,
        ]);
    }

    private function writeAnnotations($code, $data)
    {
        $previousKey = null;

        foreach ($data as $key => $value) {
            $replaced = 0;
            $code = preg_replace('~([\s#\/\/*]+@' . $key . '\s+)(.+)~', '${1}' . $value, $code, 1, $replaced);

            if ($replaced === 0) {
                // term not found
                $code = preg_replace('~([\s#\/\/*]+@)(' . $previousKey . '\s+)(.+\n)~', '$1$2$3${1}' . $key . ' ' . $value, $code, 1, $replaced);

                if ($replaced === 0) {
                    throw new \Exception('Could not write annotation ' . $key);
                }
            }

            $previousKey = $key;
        }

        return $code;
    }

    private function extractAnnotations($code)
    {

        preg_match_all('~\s*(#|\/\/)\s+@([a-z]+)\s+(.+)~', $code, $matches);

        $annotations = [];

        for ($i = 0; $i < count($matches[0]); $i++) {
            $key = $matches[2][$i];
            $value = $matches[3][$i];

            // Store only the first occurence of a annotation.
            $annotations[$key] = $annotations[$key] ?? $value;
        }

        return $annotations;
    }

    public function listScripts() {
        return array_map('basename', glob($_ENV['DEVTOOLS_EXEC_PATH'] . '/**', GLOB_ONLYDIR));
    }
    public function searchScript($term)
    {
        $list = $this->listScripts();

        return preg_grep('~' . $term . '~', $list);
    }

    public function openScript($file)
    {
        $dir = $_ENV['DEVTOOLS_EXEC_PATH'] . '/' . $file;
        if (file_exists($dir . '/script.txt')) {
            return file_get_contents($dir . '/script.txt');
        }
        return false;
    }

    public function getExecutor(&$context)
    {

        if ($type = $context['meta']['type'] ?? false) {
            $class = "\\Devtools\\Exec\\{$type}Executor";

            if (class_exists($class)) {
                return new $class($context);
            }
        }

        // default
        return new DefaultExecutor($context);
    }
}