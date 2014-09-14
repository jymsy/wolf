wolf : A process manager in PHP
====

Directory structure
-------------------
```
bin/                 php bootstrap
conf/               configure file
src/                 mainly source code
var/                 default location of log file
webmanager/           a web page manager (optional)
```


PHP Requirements
-------------------
 * PHP 5.3.? - not sure exact version
 * POSIX extension
 * pcntl extension
 * pthreads extension
 
What can wolf do?
-------------------
 * Managing process.(start, stop, restart..)
 * Catching process output to log file.
 * Sending email when process stopped.
 
How to use?
-------------------
 * Rename conf/wolf.conf.dev to conf/wolf.conf.
 * Modify wolf.conf.
 * run "bin/wolfd"
 * use "bin/wolfctl help" for help 