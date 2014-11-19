Collection of tools I using during PHP development using Emacs

php-etags.php
--------------
Creates a different `TAGS` file format to differentiate between methods and functions and associate `new Object()` with the right method `__constructor()`.

php.el
------
Some more functions to deal with PHP development.

* M-x php-run-string executes one line of PHP code. It can't handle more than one statement; Use echo or print statements

* M-x php-run-buffer executes the buffer via the PHP CLI

* M-x use-superglobals to change deceprated `$HTTP_X_VARS` into the superglobals `$_X`

* M-x php-debug-file debug file with xdebug & PHP CLI (and [geben](http://code.google.com/p/geben-on-emacs/))

* php-after-save-hook updates the `TAGS` file (using tag-fucker.php) every time you save a PHP file. It's installed automatically.

* M-x php-xref tries to find every call of a function using grep. Searches in subdirectories using the location of the `TAGS` file as root.
