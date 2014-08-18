wolf : A process manager in PHP
====

PHP Requirements
----------------

 * PHP 5.3.? - not sure exact version
 * POSIX extension
 * pcntl extension
 * pthreads extension
 
What can wolf do?
============
 * Managing process.(start, stop, restart..)
 * Catching process output to log file.
 * Sending email when process stopped.
 
How to use?
============
 * Rename conf/wolf.conf.dev to conf/wolf.conf.
 * Modify wolf.conf.
 * run "bin/wolfd"
 * use "bin/wolfctl help" for help 