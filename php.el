(defun use_superglobals ()
  "Replace HTTP_X_VARS with _X in current buffer"
  (interactive)
  (save-excursion
    (goto-char (point-min))
    (query-replace-regexp "HTTP_\\(POST\\|GET\\|SERVER\\|SESSION\\|COOKIE\\)_VARS" "_\\1")))
(global-set-key "\C-cs" 'use_superglobals)

(defun php-run-string (code doInsert)
  (interactive "sPHP Code: \nP")
  (let ((result (shell-command-to-string (format "php -r '%s;'" code))))
    (if doInsert
        (insert result)
        (message "%s" result))))

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
  "check syntax after saving php-file & update TAGS file"
  (when (string-match "\.php$" buffer-file-truename)
    (update-tag-file)
    (php-check-syntax)))

(add-hook 'after-save-hook 'php-after-save-hook)

