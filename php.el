(defun use-superglobals ()
  "Replace HTTP_X_VARS with _X in current buffer"
  (interactive)
  (save-excursion
    (goto-char (point-min))
    (query-replace-regexp "HTTP_\\(POST\\|GET\\|SERVER\\|SESSION\\|COOKIE\\)_VARS" "_\\1")))
(global-set-key "\C-cs" 'use_superglobals)

(defun php-run-string (code doInsert)
  "Run a line! of PHP code. Use echo to see a result. Call with any
prefix to insert the result"
  (interactive "sPHP Code: \nP")
  (let* ((code (replace-regexp-in-string "\"" "\\\\\"" code))
         (result (shell-command-to-string (format "php -r \"%s;\"" code))))
    (if doInsert
        (insert result)
        (message "php -r \"%s;\"" code)
        (message "%s" result))))

;; Syntax check the current buffer
;; using CLI php -l
;; (Buffer has to be saved)
(defun php-run-buffer ()
  "Run buffer in the PHP CLI"
  (interactive)
  (let ((msg nil))
    (if (not (buffer-modified-p))
        (setq msg (shell-command-to-string (format "php -f %s" (buffer-file-name))))
      (let ((tmp-name (make-temp-name "/tmp/phprun"))
            (content (buffer-string)))
        (with-temp-file tmp-name
          (insert content))
        (setq msg (shell-command-to-string (format "php -f %s" tmp-name)))
        (delete-file tmp-name)))
    (message msg)
    t))

(defun php-debug-file ()
  "Run file in CLI with debugger enabled. geben is an emacs frontend for xdebug"
  (interactive)
  (if (not (buffer-modified-p))
      (async-shell-command (format "export  XDEBUG_CONFIG=\"idekey=geben-xdebug\"; php -f %s" (buffer-file-name)))
        (message "Save file before debugging!"))
    t)

;; Notes:
;; - This updates into the current TAGS file. So if you switch
;;   between projects make sure you visit the right TAGS file before
;;   using this
;; - I don't know if it works with multiple TAGS files
;; - uses tag-fucker.php so either use that & my modified php-mode
;;   or modify it to use etags ( "etags -o- <file>" prints)
(defun update-tag-file ()
  "update the TAGS from the current buffer"
  (when tags-file-name
    (let ((root (substring tags-file-name
                           0 (- (length tags-file-name) 4))))
      (save-current-buffer
        (let ((buf (find-file-noselect tags-file-name))
              (p-file (buffer-file-name)))
          (set-buffer buf)
          (goto-char (point-min))
          (when (search-forward (substring p-file (length root)) nil t)
            (let ((start (search-backward ""))
                  (end (search-forward "" nil t 2)))
              (delete-region start (or (and end (1- end)) (point-max)))
              (save-buffer buf)))
          (shell-command
           (format "tag-fucker.php %s >> %s" p-file tags-file-name) nil)
          (revert-buffer t t)
          (visit-tags-table tags-file-name))))
    nil))

;; shortcut to rebuild table
(defun php-rebuild-completion-table ()
  (interactive)
  (setq php-completion-table nil)
  (php-completion-table))

(defun php-after-save-hook ()
  "check syntax after saving php-file & update TAGS file
iff the file is in the same path as the TAGS file"
  (when (and (string-match "\.php$" buffer-file-truename)
             tags-file-name)
    (let* ((l (- (length tags-file-name) 4))
           (tp (substring tags-file-name
                          0 l))
           (bp (substring buffer-file-truename
                          0 (min l (length buffer-file-truename)))))
      (when (string-equal tp bp)
        (message "* yup update TAGS *")
        (update-tag-file)))
    (php-check-syntax)))

(add-hook 'after-save-hook 'php-after-save-hook)

;; PHP XREF via grep
(defun php-xref (func)
  "Try to find every call of func"
  (interactive (list (read-string "PHP Function: " (current-word t))))
  (let ((buffer-name "*php-xref*")
        (msg nil)
        (root (substring tags-file-name
                         0 (- (length tags-file-name) 4)))
        (func (replace-regexp-in-string "\"" "\\\\\"" func)))
    (message (format "cd %s; grep -F -r -n -o --include='*.php' '\\<%s\\>' *" root func))
    (setq msg (shell-command-to-string (format "cd %s; grep -F -r -n -o --include='*.php' '%s' *" root func)))
    (if (string= msg "")
        (progn (message "no references found") 
               ;; clear buffer
               (if (get-buffer buffer-name)
                   (save-excursion
                     (set-buffer (get-buffer buffer-name))
                     (delete-region 1 (point-max))
                     (insert "No references found")))
               nil)
        (if (get-buffer buffer-name)
            (kill-buffer buffer-name))
        (let ((buf (get-buffer-create buffer-name)))
          (save-excursion
            (set-buffer buf)
            (insert msg)
            (goto-char (point-min))
            (let ((start 0)
                  (last -1))
              (while
                  (setq start
                        (search-forward-regexp
                         "^\\([^:]+\\):\\([0-9]+\\):"
                         nil t))
                (let ((map (make-sparse-keymap))
                      (F (concat root (match-string 1)))
                      (L (string-to-number (match-string 2))))
                  (define-key map
                      [mouse-1] `(lambda ()
                                   (interactive)
                                   (find-file ,F)
                                   (goto-line ,L)))
                  (message "%d;%d" (match-beginning 1) (match-end 1))
                  (put-text-property (match-beginning 1) (match-end 1) 'keymap map buf)
                  (put-text-property (match-beginning 1) (match-end 1) 'face '(:underline t :foreground "blue") buf)))
              ))
          (set-buffer buf)
          (goto-char (point-min))
          (display-buffer buf t))
        t)))


;;
;; Indentation in PHP could be better by imho following Java coding
;; standards closer:
;; if (longline
;;     && another line)
;; should be
;; if (longline
;;         && another line)
;;
;; but array(stuff,
;;           more)
;; should still line up (they're using the same var for indentation:
;;  arglist-cont-nonempty)
;;

;; missing: get-block, split-string
;; check: dolist
;; (defun php-get-undeclared-variables ()
;;   "Get a list of undeclared variables of the surrounding block.
;; \(Doesn't deal with list\(...\) = yet\)"
;;   (interactive)
;;   (let ((vars nil)
;;         (code-block (get-block)))
;;     (setq ret (shell-command-to-string (format "get-undeclared-vars.php - %s" block)))
;;     (if (string= ret "")
;;         (message "No undeclared variables")
;;         (setq vars (split-string "\n" ret))
;;         (dolist (vars v)
;;           (setq v (split-string ";"))
;;           (message (car v))))))
