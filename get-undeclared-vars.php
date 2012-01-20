#!/usr/bin/php -d xdebug.profiler_enable=On
<?php
// #!/usr/bin/php


/**
goal: print all variables who are being used without having any value assigned
and when assigned would "ensure" that all other variables get assigned a value
value includes null,0,'',false

meet var:
is assignment
  then save var as assigned
elseif (!assigned[var])
  unassigned[var] = line;offset
else
  nothing


Assignment ops:
"="

(warning wenn kein anderer vorher:
T_AND_EQUAL
T_CONCAT_EQUAL
T_MINUS_EQUAL
T_PLUS_EQUAL
T_DIV_
T_MOD_
T_MUL_
T_SR_EUQAL
T_SL_EUQAL
T_OR_EUQAL
T_XOR_EUQAL

bugs: doesn't handle assignments with foreach ($foo as $bar => $baz)

 */

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
        $line = strip_dotdash($argv[$i]);
        tag_file($line);
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
/*
Tags and Numbers
315: T_CONSTANT_ENCAPSED_STRING
333: T_FUNCTION
352: T_CLASS
365: T_COMMENT
367: T_OPEN_TAG
369: T_CLOSE_TAG
370: T_WHITESPACE
341: T_PUBLIC
342: T_PROTECTED
343: T_PRIVATE
346: T_STATIC
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
        $lines = file($file);
        $source = join("", $lines);
        $tokens = token_get_all($source);
        unset($source);
        //$curly = 0;
        $assigned  =
            array('$_GET'    => true,
                  '$GLOBALS' => true,
                  '$_POST'   => true,
                  '$_COOKIE' => true,
                  '$_SERVER' => true,
                  '$_ENV'    => true);

        // TODO doesn't handle:
        // $this doable?
        // function args doable
        // global doable
        // arrays only to limited extend
        // object fields only those directly declared in the class

        $unassigned = array();
        $assign  = array();
        $var_tok = null;
        $p_level = 1; // Verschachtelung von () + 1 damit == true
        foreach ($tokens as &$t) {
            /*
            if (is_array($t)) {
                echo 'T:'.token_name($t[0]).":".$t[1]."\n";
            }
            else {
                echo 'S:'.$t."\n";
            }
            */
            if (is_array($t) && ($t[0] == T_VARIABLE || $t[0] == T_STRING_VARNAME)) {
                $var_tok = $t;
                if ($t[0] == T_STRING_VARNAME) {
                    $var_tok[1] = '$'.$t[1];
                }
            }
            elseif ($t == '(') {
                $p_level++;
            }
            // Pro schließender Klammer die Variablen abschließen die auf dem level vorkamen
            elseif ($t == ')') {

                if (!empty($assign)) {
                    if ($var_tok && !$assigned[$var_tok[1]]) {
                        if (!$unassigned[$var_tok[1]]) {
                            $unassigned[$var_tok[1]] = array();
                        }
                        $unassigned[$var_tok[1]][] = $var_tok[2];
                    }
                    $p_assign = array();
                    foreach ($assign as $var => $l) {
                        if ($l == $p_level) {
                            $p_assign[$var] = true;
                            unset($assign[$var]);
                        }
                    }
                    $assigned = array_merge($assigned, $p_assign);

                    $var_tok = null;
                }
                $p_level--;
            }
            elseif ($var_tok && $t == '=') {
                $assign[$var_tok[1]] = $p_level;
                $var_tok = null;
            }
            elseif (!empty($assign) && $t == ';') {
                if ($var_tok && !$assigned[$var_tok[1]]) {
                    if (!$unassigned[$var_tok[1]]) {
                        $unassigned[$var_tok[1]] = array();
                    }
                    $unassigned[$var_tok[1]][] = $var_tok[2];
                }
                $assigned = array_merge($assigned, $assign);

                $var_tok = null;
                $assign = array();
            }
            elseif (is_array($t) && ($t[0] == T_WHITESPACE || $t[0] == T_COMMENT)) {
                continue;
            }
            elseif ($var_tok && !$assigned[$var_tok[1]]) {
                if (!$unassigned[$var_tok[1]]) {
                    $unassigned[$var_tok[1]] = array();
                }
                $unassigned[$var_tok[1]][] = $var_tok[2];
                $var_tok = null;
            }
            else {
                continue;
            }

        }
        foreach ($unassigned as $name => $lines) {
            echo $name.':';
            echo join(';', $lines);

            echo "\n";
        }
    }

}
?>