Description
===========

Sphinx Search integration for vBulletin.

Directory content:

    /dictionaries   - files & tools to build wordforms/stopwords
    /forum_upload   - vBulletin addon files
    /lang           - language translations for vB addon
    /system_upload  - server configs for sphinx
    /tools          - debug toys

Installation
============

**Attention!** This software **requires sphinx 1.11**. It's currently available only from SVN repository [[http://code.google.com/p/sphinxsearch/source/checkout]]. Don't install 1.10beta or previous, it doesn't have all features, used by this product.

System configs are written for ubuntu 10.04. Probably, cron, init & monit configs should be changed in your case. Take care of `./system/etc` folder.

This software was tested with 64-bit systems only.

Install sphinx daemon
---------------------

Download latest 1.11 sphinx from svn [[http://code.google.com/p/sphinxsearch/source/checkout]] (see instructions there). Then unpack, configure with 64-bit ids, build & install.

    cd sphinx_src_dir
    ./configure --enable-id64
    ./make
    ./make install

If you have ubuntu 10.04 - just copy contents of `./system` folder to your server and edit sphinx configs:

- **/usr/local/etc/sphinx-conf.php** - generic config, see sample in the same directory
- **/usr/local/sphinx/sphinx.conf.tpl** - searchd daemon config template. Probably, need changes, if you wish to modify search zones
- **/usr/local/sphinx/sphinx_xmlpipe_conf.ini** - indexer config. Contains cleanup rules & indexes descriptions.

**Note.** It's strongly recommented to use morthology/stopwords dictionaries, instead simple stemming. You can find some tools in `./dictionaries` folder. But prior to use that - read sphinx documentation, to uderstand technology.

Update autostart, run full reindex, start search daemon & restart cron

    update-rc sphinx defaults
    service sphinx index
    service sphinx start
    service cron restart

Now you have search daemon running. It's time to install vBulletin plugin to use new search.

Install vBulletin plugin
------------------------

Upload files from `./forum_upload` folder to forum root.

Make changes in forum config `your_forum_root_dir/includes/config.php` . Add some specific variables:

    // Sphinx Search
    $config['sphinx']['sql_host'] = '/tmp/sphinxql.sock';
    //$config['sphinx']['sql_port'] = 9312;

    // MySQL socket for xmlpipe2 data source
    $config['sphinx']['src_sql_socket'] = '/var/run/mysqld/mysqld.sock';

Install XML file in vB ACP. If needed - install translation from `./lang`

Go to ACP, enable product & make final tining

    ACP -> Settings -> Search Type = sphinx
    ACP -> Settings -> Options -> Search -> ... (marked with 'sphinx search' comment)

