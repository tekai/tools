#!/usr/bin/php
<?php
// #!/usr/bin/php -d xdebug.profiler_enable=On
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
        $line = strip_dotdash($line);
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
    $defs = array();
    $offset = 0;
    $function = false;
    $class    = false;
    $define   = false;
    $stringp  = false;
    $curly = 0;
    $line = 0;
    if (file_exists($file)) {
        echo chr(12)."\n".$file;
        $lines = file($file);
        $source = join("", $lines);
        $tokens = token_get_all($source);
        unset($source);
        //$curly = 0;
        foreach ($tokens as &$t) {

            if (is_array($t)) {
                if ($t[0] == T_FUNCTION) {
                    $function = true;
                    $def = array('line' => $t[2], 'offset' => $offset);
                }
                elseif ($t[0] == T_CLASS) {
                    $class = true;
                    $className = '';
                }
                elseif ($t[0] == T_EXTENDS) {
                    $extends = true;
                }
                elseif ($extends && $t[0] == T_STRING) {
                    $extends = false;
                }
                elseif ($class && !$curly && $t[0] == T_STRING) {
                    $className = $t[1];
                }
                // class or function/method name
                elseif ($function && $t[0] == T_STRING) {
                    if ($class && $curly && ($t[1] == '__construct' ||
                                             $t[1] == $className)) {
                        $type = 'constructor';
                    }
                    elseif ($class && $curly) {
                        $type = 'method';
                    }
                    else {
                        $type = 'function';
                    }

                    $def['search'] = 'function '.$t[1];
                    preg_match('/^(.*)'.preg_quote($t[1], '/').'/', $lines[$t[2]-1], $m);
                    if (!empty($m)) {
                        $def['search'] = $m[0];
                        // offset has to point to the start of the line
                        $def['offset'] -= strlen($m[1]) -9;
                    }
                    if ($type == 'constructor') {
                        $def['name'] = $type.' '.$className;
                    }
                    else {
                        $def['name'] = $type.' '.$t[1];
                    }
                    if ($function) {
                        $defs[$def['name']] = $def['search'].chr(127).$def['name'].chr(1).$def['line'].','.$def['offset']."\n";
                        $def = array();
                        $function = false;
                    }

                }
                // T_STRING define
                elseif ($t[0] == T_STRING && $t[1] == 'define') {
                    $define = true;
                    $def = array('line' => $t[2], 'offset' => $offset);
                }
                // T_CONSTANT_ENCAPSED_STRING aka 'string' or "string"
                elseif ($t[0] == T_CONSTANT_ENCAPSED_STRING && $define) {

                    $define = false;
                    $def['search'] = 'define('.$t[1];
                    preg_match('/^(.*)'.preg_quote($t[1],'/').'/', $lines[$t[2]-1], $m);
                    if ($m) {
                        $def['search'] = $m[0];
                        // offset has to point to the start of the line
                        $def['offset'] -= strlen($m1);
                    }
                    $def['name'] = substr($t[1],1,-1);
                    $defs[$def['name']] = $def['search'].chr(127).$def['name'].chr(1).$def['line'].','.$def['offset']."\n";
                    $def = array();

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
                    if ($class)
                        $curly++;
                }
                elseif ($t == '}' && $class && !$stringp) {
                    $curly--;
                    if (!$curly)
                        $class = false;
                }

                $offset += strlen($t);
            }
        }
        $tags = join("", $defs);
        unset($defs);
        unset($lines);
        echo ',',strlen($tags),"\n",
            $tags;
       
    }

}
?>