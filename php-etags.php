#!/usr/bin/php
<?php
error_reporting (E_ALL & ~E_NOTICE);
// #!/usr/bin/php
// #!/usr/bin/php -d xdebug.remote_autostart
// #!/usr/bin/php -d xdebug.profiler_enable=On

/*
 * Doesn't tag interfaces, traits, const constants
 */
$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];
if ($argc < 2) {
    exit;
}
elseif ($argc == 2 && $argv[1] == '-') {
    while ($line = trim(fgets(STDIN))) {
        $line = strip_dotdash($line);
        tag_file($line);
    }
}
else {
    for ($i=1;$i<$argc;$i++ ) {
        $line = strip_dotdash($argv[$i]);
        tag_file($argv[$i]);
    }
}
function strip_dotdash($file) {
    if (substr($file, 0, 2) == './')
        $file = substr($file, 2);
    return $file;
}
/*
etags format
{src_file},{size_of_tag_definition_data_in_bytes}
suche => suchbegriff
{tag_definition_text}<\x7f>{tagname}<\x01>{line_number},{byte_offset}
 */

function tag_file($file) {
    $defs         = array();
    $offset       = 0;
    $functionp    = false;
    $class        = false;
    $define       = false;
    $stringp      = false;
    $constp       = false;
    $extends      = false;
    $interfacep   = false;
    $className    = '';
    $constructorp = false;
    $curly        = 0;
    $line         = 0;

    $ns_arr       = array();
    $namespace    = '';
    $nsp          = false;

    if (file_exists($file)) {
        $lines = file($file);
        $source = join("", $lines);
        $tokens = token_get_all($source);
        unset($source);

        foreach ($tokens as &$t) {

            if (is_array($t)) {
                if ($t[0] === T_NAMESPACE) {
                    $nsp = true;
                    $ns_arr = array();
                    $namespace = '';
                }
                elseif ($nsp && $t[0] === T_STRING) {
                    $ns_arr[] = $t[1];
                }
                elseif ($t[0] === T_FUNCTION) {
                    $functionp = true;
                    $def = array('line' => $t[2], 'offset' => $offset);
                }
                elseif ($t[0] === T_CLASS) {
                    $class = true;
                    $className = '';
                    $classdef = array('line' => $t[2], 'offset' => $offset);
                    $constructorp = false;
                }
                elseif ($t[0] === T_EXTENDS) {
                    $extends = true;
                }
                elseif ($t[0] === T_INTERFACE) {
                    $interfacep = true;
                }
                elseif ($interfacep && !$curly && $t[0] === T_STRING) {
                    $type = 'interface';
                    $def['search'] = 'interface '.$t[1];
                    preg_match(
                        '/^(.*)'
                        .preg_quote($def['search'], '/')
                        .'/',
                        $lines[$def['line']-1],
                        $m);
                    if (!empty($m)) {
                        $def['search'] = $m[0];
                        // offset has to point to the start of the line
                        $def['offset'] -= strlen($m[1]);
                    }
                    $def['name'] = $type.' '.$t[1];
                    if ($namespace) {
                        $def['ns-name'] = $type.' '.$namespace.$t[1];
                    }
                    add_def($defs, $def);
                }
                elseif ($t[0] === T_CONST) {
                    $constp = true;
                    $def = array('line' => $t[2], 'offset' => $offset);
                }
                elseif ($extends && $t[0] === T_STRING) {
                    $extends = false;
                }
                elseif ($class && !$curly && !$className && $t[0] === T_STRING) {
                    $className = $t[1];
                    // hmm merken wo die klasse anfaengt und wenn
                    // $constructorp == false am ende der Klasse/Schleife
                    // dann Klassendef als constructor
                }
                // class or function/method name
                elseif ($functionp && $t[0] === T_STRING) {
                    if ($class && $curly && ($t[1] == '__construct' ||
                                             $t[1] == $className)) {
                        $type = 'constructor';
                        $constructorp = true;
                    }
                    elseif (($class || $interfacep) && $curly) {
                        $type = 'method';
                    }
                    else {
                        $type = 'function';
                    }

                    $prefix = 'function';
                    $def['search'] = $prefix.' '.$t[1];

                    preg_match('/^(.*)'.preg_quote($t[1], '/').'/', $lines[$t[2]-1], $m);
                    if (!empty($m)) {
                        $def['search'] = $m[0];
                        // offset has to point to the start of the line
                        $def['offset'] -= strlen($m[1]) - (strlen($prefix) + 1);
                    }
                    if ($type == 'constructor') {
                        $def['name'] = $type.' '.$className;
                        if ($namespace) {
                            $def['ns-name'] = $type.' '.$namespace.$className;
                        }
                    }
                    elseif ($type == 'method') {
                        $def['name'] = $type.' '.$t[1];
                    }
                    else {
                        $def['name'] = $type.' '.$t[1];
                        if ($namespace) {
                            $def['ns-name'] = $type.' '.$namespace.$t[1];
                        }
                    }
                    if ($functionp) {
                        add_def($defs, $def);
                        $def = array();
                        $functionp = false;
                    }

                }
                // T_STRING define
                elseif ($t[0] === T_STRING && $t[1] == 'define') {
                    $define = true;
                    $def = array('line' => $t[2], 'offset' => $offset);
                }
                elseif ($constp && $t[0] === T_STRING) {
                    $def['search'] = 'const '.$t[1];
                    preg_match('/^(.*)'.preg_quote($t[1],'/').'/', $lines[$t[2]-1], $m);
                    if ($m) {
                        $def['search'] = $m[0];
                        // offset has to point to the start of the line
                        $def['offset'] -= strlen($m[1]) - 6;
                    }
                    $def['name'] = $t[1];
                    if ($namespace) {
                        $def['ns-name'] = $namespace.$t[1];
                    }
                    add_def($defs, $def);
                    $def = array();

                }
                // T_CONSTANT_ENCAPSED_STRING aka 'string' or "string"
                elseif ($t[0] === T_CONSTANT_ENCAPSED_STRING && $define) {

                    $define = false;
                    $def['search'] = 'define('.$t[1];
                    preg_match('/^(.*)'.preg_quote($t[1],'/').'/', $lines[$t[2]-1], $m);
                    if ($m) {
                        $def['search'] = $m[0];
                        // offset has to point to the start of the line
                        $def['offset'] -= strlen($m[1]);
                    }
                    $def['name'] = substr($t[1],1,-1);
                    add_def($defs, $def);
                    $def = array();

                }
                elseif ($t[0] === T_START_HEREDOC) {
                    $stringp = true;
                }
                elseif ($t[0] === T_END_HEREDOC) {
                    $stringp = false;
                }
                else {
                    //echo $t[0].':'.token_name($t[0])."\n";
                }
                $offset += strlen($t[1]);
            }
            else {
                /* "{$foo[0]}" is openend by T_CURLY_OPEN but closed by a ordinary } */
                if ($t == '"') {
                    $stringp = !$stringp;
                }
                elseif ($t == '{' && !$stringp) {
                    if ($nsp) {
                        $nsp = false;
                        $namespace = implode('\\', $ns_arr).'\\';
                    }
                    elseif ($class || $interfacep) {
                        $curly++;
                    }
                }
                elseif ($t == '}' && $class && !$stringp) {
                    $curly--;
                    // end of class definition?
                    if (!$curly) {
                        $class = false;

                        // put in fake constructor pointing to class def
                        if (!$constructorp) {
                            $type = 'constructor';
                            $classdef['search'] = 'class '.$className;
                            preg_match(
                                '/^(.*)'
                                .preg_quote($classdef['search'], '/')
                                .'/',
                                $lines[$classdef['line']-1],
                                $m);
                            if (!empty($m)) {
                                $classdef['search'] = $m[0];
                                // offset has to point to the start of the line
                                $classdef['offset'] -= strlen($m[1]);
                            }
                            $classdef['name'] = $type.' '.$className;
                            if ($namespace) {
                                $classdef['ns-name'] = $type.' '.$namespace.$className;
                            }
                            add_def($defs, $classdef);
                        }
                    }
                }
                elseif ($t == '}' && $interfacep && !$stringp) {
                    $curly--;
                }
                // anonymous function
                elseif ($t == '(' && $functionp) {
                    $functionp = false;
                    $def = array();
                }
                elseif ($t == ';') {
                    if ($nsp) {
                        $nsp = false;
                        $namespace = implode('\\', $ns_arr).'\\';
                    }
                    $constp = false;
                }
                $offset += strlen($t);
            }
        }
        if (!empty($defs)) {
            ksort($defs);
            $tags = join("", $defs);
            $defs = array();
            unset($lines);
            echo chr(12)."\n".$file;
            echo ',',strlen($tags),"\n",
                $tags;
        }
    }
}

function add_def(&$defs, $def) {
    $defs[$def['name']] = ''
        .$def['search'].chr(127)
        .$def['name'].chr(1)
        .$def['line'].','
        .$def['offset']."\n";

    if ($def['ns-name']) {
        $defs[$def['ns-name']] = ''
            .$def['search'].chr(127)
            .$def['ns-name'].chr(1)
            .$def['line'].','
            .$def['offset']."\n";

    }
}