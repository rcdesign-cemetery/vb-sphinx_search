<?php
/**
 * Do not change
 */
error_reporting(0);
ob_start();

define('SPHINX_CONF_TPL_FILE', dirname(__FILE__) . '/sphinx.conf.tpl');

$vbulletin_conf_path = $vbulletin_root_path . '/includes/config.php';
if (file_exists($vbulletin_conf_path))
{
    require_once($vbulletin_conf_path);
}
else
{
    echo 'Check path to vbulletion root directory';
    die();
}

if (!isset($sphinx_conf['mem_limit']))
{
    $sphinx_conf['mem_limit'] = '256';
}
if (!isset($sphinx_conf['query_log']))
{
    $sphinx_conf['query_log'] = '/dev/null';
}
if (!isset($sphinx_conf['log_file']))
{
    $sphinx_conf['log_file'] = '/dev/null';
}
if (!isset($sphinx_conf['read_timeout']))
{
    $sphinx_conf['read_timeout'] = 5;
}
if (!isset($sphinx_conf['max_children']))
{
    $sphinx_conf['max_children'] = 5;
}
if (!isset($sphinx_conf['max_matches']))
{
    $sphinx_conf['max_matches'] = 50000;
}

if (isset($config['MasterServer']['servername']) AND (!empty($config['MasterServer']['servername'])))
{
    $sphinx_conf['db_host'] = $config['MasterServer']['servername'];
}
else
{
    $sphinx_conf['db_host'] = 'localhost';
}
$sphinx_conf['db_user'] = $config['MasterServer']['username'];
$sphinx_conf['db_pass'] = $config['MasterServer']['password'];
$sphinx_conf['db_name'] = $config['Database']['dbname'];
$sphinx_conf['table_prefix'] = $config['Database']['tableprefix'];

if (isset($sphinx_conf['db_sock']) AND !empty($sphinx_conf['db_sock']))
{
    $sphinx_conf['db_sock'] = 'sql_sock        = ' . $sphinx_conf['db_sock'];
    $sphinx_conf['db_port'] = '';
}
else
{
    $sphinx_conf['db_sock'] = '';
    $sphinx_conf['db_port'] = 'sql_port        = ' . $config['MasterServer']['port'];
}
if (isset($sphinx_conf['sphinx_stopwords_file']) AND !empty($sphinx_conf['sphinx_stopwords_file']))
{
    $sphinx_conf['sphinx_stopwords_file'] = 'stopwords       = '.$sphinx_conf['sphinx_stopwords_file'];
}
else
{
    $sphinx_conf['sphinx_stopwords_file'] = '';
}
if (isset($sphinx_conf['sphinx_wordforms_file']) AND !empty($sphinx_conf['sphinx_wordforms_file']))
{
    $sphinx_conf['sphinx_wordforms_file'] = 'stopwords       = '.$sphinx_conf['sphinx_wordforms_file'];
}
else
{
    $sphinx_conf['sphinx_wordforms_file'] = '';
}

//sphinx_ql
$host = $config['sphinx']['sql_host'];
if ( $host[0] == '/')
{
    $sphinx_conf['sphinx_ql'] =  $host;
}
else
{
    $port = $config['sphinx']['sql_port'];
    $sphinx_conf['sphinx_ql'] = "$host:$port";
}
//sphinx_api
$host = $config['sphinx']['api_host'];
if ( $host[0] == '/')
{
    $sphinx_conf['sphinx_api'] =  $host;
}
else
{
    $port = $config['sphinx']['api_port'];
    $sphinx_conf['sphinx_api'] = "$host:$port";
}


$search = array();
$replace = array();
foreach ($sphinx_conf as $key=>$value)
{
    $search[] = '{'.$key.'}';
    $replace[]= $value;
}

$conf_tpl = '';
$handle = fopen(SPHINX_CONF_TPL_FILE, "r");
if ($handle)
{
    while (!feof($handle)) {
        $conf_tpl .= fread($handle, 8192);
    }
    fclose($handle);
    echo str_replace($search, $replace, $conf_tpl);
}
else
{
    echo 'Check sphinx conf template file';
}
ob_flush();
?>
