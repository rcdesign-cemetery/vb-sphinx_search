Description
-----------

Sphinx Search integration for vBulletin

/dev_files      - configs for defelopment
/dictionaries   - files & tools to build wordforms/stopwords
/forum_upload   - vBulletin addon files
/system_upload  - server configs for sphinx

Installation
------------

1. Edit vBulletin Config (./includes/config.php)

Add:

// Sphinx Search
$config['sphinx']['sql_host'] = '/tmp/sphinxql.sock';
//$config['sphinx']['sql_port'] = 9312;

// MySQL socket for xmlpipe2 data source
$config['sphinx']['src_sql_socket'] = '/var/run/mysqld/mysqld.sock';

2. Build & install sphinx from sources

!!! 1.10beta+ required
(make, make install)


3. Copy files of this addon


4. Prepare dictionaries (recommended)


5. Configure /usr/local/etc/sphinx-conf.php from example, then:

service sphinx index
service sphinx start
service cron restart


6. Install XML file in vB ACP.

ACP -> Settings -> Search Type = sphinx
ACP -> Settings -> Options -> Search -> Similar Threads Period

