#!/usr/bin/php 
<?php
#!/usr/bin/php -d xdebug.remote_autostart="on" 



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

Bugs:
- $this handling is shaky, it doesn't complain about $this in classes
  but doesn't check if $this->var has ever been declared
  (it can't check for assignment!)
- doesn't handle parameters passed with &, but those are a not common or
  such a good idea anyways

Feature:
Doesn't handle eval, variable vars, create_function() etc. because they're kinda bad
 */

// #!/usr/bin/php -d xdebug.profiler_enable=On
$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];
if ($argc < 2) {
    exit;
}
else {
    // parse script parameters
    $options = array('notags' => false);
    foreach ($argv as $i => $arg) {
        switch ($arg) {
            case '-r':
                $options['notags'] = true;
                unset($argv[$i]);
                break;
        }
    }
    $argv = array_merge($argv);
    $argc = count($argv);

    if ($argc == 2 && $argv[1] == '-') {
        while ($line = trim(fgets(STDIN))) {
            $line = strip_dotdash($line);
            tag_file($line, $options);
        }
    }
    else {
        for ($i=1;$i<$argc;$i++ ) {
            $line = strip_dotdash($argv[$i]);
            tag_file($line, $options);
        }
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
function tag_file($file, $options) {

    if (file_exists($file)) {
        $lines = file($file);
        $source = join("", $lines);
        if ($options['notags']) {
            $source = '<?php;'.$source.';?>';
        }
        $tokens = token_get_all($source);
        unset($source);
        $curly = 0;
        $assigned  = init_scope(false) ;

        // TODO doesn't handle:
        // $this doable?
        // function args doable
        // global doable
        // arrays only to limited extend
        // object fields only those directly declared in the class

        $foreach    = false;
        $as         = false;
        $parameters = false;
        $list       = false;
        $class      = false;

        $unassigned = array(
            // 'scope' => array('global')
                           );
        $assign  = array();
        $var_tok = null;
        $p_level = 1; // Verschachtelung von () + 1 damit == true
        foreach ($tokens as &$t) {
            /*
            if (is_array($t)) {
                echo 'T:'.token_name($t[0]).':'.$t[1]."\n";
            }
            else {
                echo 'S:'.$t."\n";
            }
            continue;
            */
            if (is_array($t) && ($t[0] == T_CLASS)) {
                $class = $curly + 1;
                $global_unassigned = $unassigned;
                $global_assigned = $assigned;
                $unassigned = array(
                    // 'scope' => array('class')
                                   );
            }
            elseif (is_array($t) && ($t[0] == T_FUNCTION)) {
                $parameters = $p_level + 1;
                $function = $curly + 1;
                if (!$class) {
                    $global_unassigned = $unassigned;
                    $global_assigned   = $assigned;
                }
                $assigned = init_scope($class);
                $unassigned = array(
                    // 'scope' => array('function')
                                   );
            }
            elseif (is_array($t) && ($t[0] == T_FOREACH)) {
                $foreach = true;
            }
            elseif (is_array($t) && ($t[0] == T_AS)) {
                $as = $p_level;
            }
            elseif (is_array($t) && ($t[0] == T_LIST)) {
                $list = $p_level + 1;
            }

            if (is_array($t) && ($t[0] == T_VARIABLE || $t[0] == T_STRING_VARNAME)) {
                $var_tok = $t;
                if ($t[0] == T_STRING_VARNAME) {
                    $var_tok[1] = '$'.$t[1];
                }
                if ($foreach === true && $as && $as <= $p_level) {
                    $assigned[$var_tok[1]] = true;
                    $var_tok = null;
                }
                elseif ($list === $p_level) {
                    $assigned[$var_tok[1]] = true;
                    $var_tok = null;
                }
                elseif ($parameters === $p_level) {
                    $assigned[$var_tok[1]] = true;
                    $var_tok = null;
                }
            }
            elseif ($t == '{') {
                $curly++;
            }
            elseif ($t == '}') {
                if ($function === $curly) {
                    print_unassigned($unassigned);
                    $function = false;
                    if (!$class) {
                        $unassigned = $global_unassigned;
                        $assigned   = $global_assigned;
                    }
                    $var_tok = null;
                }
                elseif ($class === $curly) {
                    $class = false;
                    $unassigned = $global_unassigned;
                    $assigned   = $global_assigned;
                }
                $curly--;
            }
            elseif ($t == '(') {
                $p_level++;
            }
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
                if ($foreach === true && $as && $as == $p_level) {
                    $foreach = false;
                    $as = false;
                }
                elseif ($list == $p_level) {
                    $list = false;
                }
                elseif ($parameters == $p_level) {
                    $parameters = false;
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
        print_unassigned($unassigned);
    }

}
function init_scope($classp) {
    $return = array(
        '$_GET'    => true,
        '$GLOBALS' => true,
        '$_POST'   => true,
        '$_COOKIE' => true,
        '$_SERVER' => true,
        '$_ENV'    => true);

    if ($classp) {
        $return['$this'] = true;
    }
    return $return;
}
function print_unassigned($unassigned) {
    foreach ($unassigned as $name => $lines) {
        echo $name.':';
        echo join(';', $lines);

        echo PHP_EOL;
    }
}
?>