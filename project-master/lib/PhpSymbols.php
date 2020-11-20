<?php

class PhpSymbols {

    function parse_test() {
        $file = __DIR__ . '/testfile.php';

        return $this->parse($file);
    }
    function consumeNextBlock(&$tokens, $callback) {
        $header = [];
        $body = [];
        $storage = &$header;
        $level = -1;
        while($token = next($tokens)) {
            if ($token === '{') {
                $storage = &$body;
                $level++;
            }

            $storage[] = $token;

            if ($token === '}') {
                $level--;
                if ($level == -1) break;
            }
        }
        $callback($header, $body);
    }

    function is($token, $type) {
        if (is_int($type) && isset($token[0])) {
            return $token[0] === $type;
        } else if (!is_int($type)) {
            return $token === $type;
        }
    }

    function seek(&$tokens, $type) {
        while($t = next($tokens)) {
            if ($this->is($t, $type)) {
                return $t;
            }
        }
    }
    function record(&$tokens, $type) {
        $buffer = [];
        while($t = next($tokens)) {
            if ($this->is($t, $type)) {
                return $buffer;
            }
            $buffer[] = $t;
        }
        return $buffer;
    }

    function content($token) {
        if (empty($token)) {
            return '';
        }
        if (is_array($token)) {
            return $token[1];
        }
        return $token;
    }

    var $registerBodies = false;

    function register($symbol) {
        if (!$this->registerBodies) {
            unset($symbol['body']);        
        }
        $this->symbols[] = $symbol;
    }

    function parse($file = null) {
        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $tokens = token_get_all($content);
        $namespace = '';
        $namespacePrefix = '';
        $this->symbols = [];

        $lastComment = null;
        while(false !== ($token = next($tokens))) {
            if ($this->is($token, T_NAMESPACE)) {
                $namespace = trim($this->print($this->record($tokens, ';')));
                $namespacePrefix = "$namespace\\";
                // echo "Namespace $namespace";
                continue;
            }
            if ($this->is($token, T_DOC_COMMENT) || $this->is($token, T_COMMENT)) {
                $lastComment = $this->content($token);
            }
            if ($this->is($token,T_CLASS)) {
                $this->consumeNextBlock($tokens, function($header, $body) use ($namespacePrefix, &$lastComment) {
                    $className = $namespacePrefix . $this->content($this->seek($header, T_STRING));
                    $this->register([
                        'class' => $className,
                        'body' => $this->print($body),
                        'comment' => $lastComment
                    ]);
                    $lastComment = null;
                    while ($bd = next($body)) {
                        if ($this->is($bd, T_DOC_COMMENT) || $this->is($bd, T_COMMENT)) {
                            $lastComment = $this->content($bd);
                        }
                        if ($this->is($bd, T_FUNCTION)) {
                            $this->consumeNextBlock($body, function ($fn_header, $fn_body) use ($className, &$lastComment) {
                                $methodName = $this->content($this->seek($fn_header, T_STRING));
                                $args = $this->print($this->record($fn_header, null));
                                $body = $this->print($fn_body);
                                $this->register([
                                    'method' => $className.'::'.$methodName,
                                    'args' => $args,
                                    'body' => $body,
                                    'comment' => $lastComment
                                ]);
                                $lastComment = null;
                            });
                        }           
                    }
                });
                continue;
            }

            if ($this->is($token, T_FUNCTION)) {
                $this->consumeNextBlock($tokens, function ($fn_header, $fn_body) use ($namespacePrefix, &$lastComment) {
                    $functionName = false;
                    while($t = next($fn_header)) {
                        if ($this->is($t, '(')) {
                            // anonymous function... 
                            break;
                        }
                        if ($this->is($t, T_STRING)) {
                            $functionName = $this->content($t);
                            break;
                        }
                    }
                    $lastComment = null;
                    if (!$functionName) {
                        return;
                    }
                    $this->register([
                        'function' => $namespacePrefix . $functionName,
                        'args' => $this->print($this->record($fn_header, null)),
                        'body' => $this->print($fn_body),
                        'comment' => $lastComment
                    ]);
                });
            }
        }

        return $this->symbols;
    }

    function type($token) {
        if (is_array($token)) {
            return token_name($token[0]) . ' ' . $token[1] . "\n";
        } else {
            return '(char) '. $token . "\n";
        }
    }
    function print($tokens, $extendedPrint = false) {
        if ($extendedPrint) {
            foreach ($tokens as $t) {
               $this->type($t);
            }
        } else {
            return join("", array_map([$this, 'content'], $tokens));
        }
    }
}