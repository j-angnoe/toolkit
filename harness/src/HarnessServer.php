<?php 
namespace harness;
use Exception;

class HarnessServer {
    function __construct(Harness $object) {
        $this->object = $object;
    }

    function setErrorHandlers() {
       $this->object->setErrorHandlers();
    }

    function handlePost() {
        $post = json_decode(file_get_contents('php://input'), 1);
        list($controller, $method, $args) = $post['rpc'];

        $controller = $this->object->loadController($controller);

        if (!$controller || !is_object($controller)) {
            throw new Exception('Controller not found or its not an object: ' . $post['rpc'][0]);
        }

        if (method_exists($controller, $method)) {
            $result = call_user_func_array([$controller, $method], $args);
        } else {
            throw new Exception($post['rpc'][0] . ' has no method ' . $method .  ', please use one of the following: ' . join("\n", get_class_methods($controller)));
        }

        if (is_iterable($result) && !is_array($result)) {
            $newResult = [];
            foreach ($result as $r) {
                $newResult[]= $r;
            }
            $result = $newResult;
        }
        $this->sendJson($result);    
    }

    function sendJson($data) {
        header('Content-type: application/json');
        $result = json_encode($data);
        if ($result === false) {
            throw new Exception('JSON Encoding of result failed: ' . json_last_error_msg() . ' ' . print_r($data, true));
        }
        exit($result);
    }

    function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method == 'POST') {
            return $this->handlePost();
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($uri == '/__isalive__') {
            exit('yup');
        }
    
        if (strpos($uri, '/dist/') === 0) {
            if (is_file($this->object->path . $uri)) {
                return $this->serveFile($this->object->path . $uri);
            } else {
                header('HTTP/1.1 404 Not found (yet)');
                exit;
            }
        }


        // starts with /harness/ ?
        if (strpos($uri, '/harness/') === 0) {
            $tryDirectories = ['/dist/', '/build/'];
            foreach ($tryDirectories as $dir) { 
                $file = $this->object->defaultHarnessPath . $dir . substr($uri, strlen('/harness/'));
                if (file_exists($file)) { 
                    return $this->serveFile($file);
                }
            }
            header('HTTP/1.1 404 Not Found');
            exit;
        }
    }
    function serveFile($file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        // @todo hier mimey implementeren.
        switch($ext) {
            case 'css': header('Content-type: text/css'); break;
            case 'js': header('Content-type: text/javascript'); break;
        }
        readfile($file);
        exit;
    }

    // Dispatch and apiBridge are a nice couple
    function getApiBridge() {
        return trim(<<<'JAVASCRIPT'
        window.api = new Proxy({},{
            get(obj, apiName) {
            return new Proxy(
                async function (...args) { 
                    var functionName = apiName;
                    var response = await axios.post(
                        "api/" + functionName,
                        { rpc: ['$default', functionName, args] }
                    );
                    return response.data;
                },
                {
                get(obj, functionName) {
                    return async function (...args) {
                    var response = await axios.post(
                        "api/" + apiName + "/" + functionName,
                        { rpc: [apiName, functionName, args] }
                    );
                    return response.data;
                    };
                }
                }
            );
            }
        });
        Vue.prototype.api = api;
JAVASCRIPT);     
    }
}
