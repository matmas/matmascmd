Matmas Commander
================

Matmas Commaner is a twin-pane PHP file manager.
Default password is `matmascmd`, change its hash in source file.

License: [GPLv3](http://www.gnu.org/licenses/gpl.html)

Features:

 - easy to deploy - one file matmascmd.php (+ optional pclzip.lib.php for zip support)
 - zip packing/unpacking (PHP-based, zip files behaving as directories)
 - tar unpacking (PHP-based)
 - text editor with line numbers
 - file upload
 - execution of system commands
 - evaluation of PHP commands
 - protection against passive password loggers. Still, use of HTTPS is recommended to prevent man-in-the-middle attack. Password is time-salted + hashed by javascript before sending it to server. Time difference is taken into account.
