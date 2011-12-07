Matmas Commander
================

It is a twin-pane file manager written in PHP.
Default password is `matmascmd`, change its hash in source file.

Features:
- easy to deploy - one file matmascmd.php (+ optional pclzip.lib.php for zip support)
- zip packing/unpacking (PHP-based, zip files behaving as directories)
- tar unpacking (PHP-based)
- text editor with line numbers
- file upload
- execution of system commands
- evaluation of PHP commands
- a bit more secure authentification without HTTPS - password is time-salted + hashed by javascript before sending it to server. Time difference is taken into account. Still, HTTPS is highly recommended.