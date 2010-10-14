Description
-----------

Sphinx Search integration for vBulletin


Installation
------------

1. Edit vBulletin Config (./includes/config.php)

Add:

// Sphinx Search
$config['sphinx']['sql_host'] = '/tmp/sphinxql.sock';
$config['sphinx']['api_host'] = '/tmp/sphinx.sock';
//$config['sphinx']['sql_port'] = 9312;
//$config['sphinx']['api_port'] = 3312;


2. Build & install sphinx from sources

!!! 1.10beta+ required
(make, make install)


3. Copy files of this addon


4. Configure /usr/local/etc/sphinx-conf.php from example, then:

service sphinx index
service sphinx start
service cron restart


5. Install XML filein vB ACP.

ACP -> Settings -> Search Type = sphinx
ACP -> Settings -> Options -> Search -> Similar Threads Period

