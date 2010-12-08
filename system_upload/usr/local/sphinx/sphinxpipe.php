#!/usr/bin/php
<?php
define('XMLPIPE_CONF_FILE', '/usr/local/sphinx/sphinx_xmlpipe_conf.ini');


/**
 * Prepare xml generator
 */

define('START_TIME', microtime(true));


// set handlers
set_exception_handler('exception_handler');
set_error_handler("error_handler");
$std_err_output = fopen("php://stderr", "w");


// parse input parameters
$index_section_name = $_SERVER['argv'][1];
$vbulletin_root_path = $_SERVER['argv'][2];


// get data from vbulletin config
include($vbulletin_root_path . '/includes/config.php');
if (!isset($config['Database']['dbname']))
{
    fwrite($std_err_output, "Invalid vBulletin config \n");
    die();
}

if (!file_exists(XMLPIPE_CONF_FILE))
{
    fwrite($std_err_output, XMLPIPE_CONF_FILE . " file not found. \n");
    die();
}


// prepare db connection settings
$db_conf['host'] = null;
$db_conf['user'] = null;
$db_conf['pass'] = null;
$db_conf['port'] = null;
$db_conf['socket'] = null;

$server_type = 'MasterServer';
if (!empty($config['SlaveServer']['username']))
{
    $server_type = 'SlaveServer';
}

$db_conf['user'] = $config[$server_type]['username'];
$db_conf['pass'] = $config[$server_type]['password'];

if (isset($config['sphinx']['src_sql_socket']))
{
    $db_conf['socket'] = $config['sphinx']['src_sql_socket'];
}
else
{
    if (isset($config[$server_type]['servername']))
    {
        $db_conf['host'] = $config[$server_type]['servername'];
    }
    if (isset($config[$server_type]['port']))
    {
        $db_conf['host'] = $config[$server_type]['port'];
    }
}
$db_conf['schema'] = $config['Database']['dbname'];

$table_prefix = $config['Database']['tableprefix'];

$mysqli = get_link($db_conf);


/**
 * Parse xmlpipe config
 */

// read config
$config = parse_ini_file(XMLPIPE_CONF_FILE, true);


// prepare replacements
$replacements = array();
if (isset($config['general']['simple_delete']))
{
    $replacements['simple_delete'] = $config['general']['simple_delete'];
}
else
{
    $replacements['simple_delete'] = array();
}
if (isset($config['general']['preg_delete']))
{
    $replacements['preg_delete'] = $config['general']['preg_delete'];
}
else
{
    $replacements['preg_delete'] = array();
}


// init index section info
$index_section = prepare_index_section($config, $index_section_name);

if (empty($index_section))
{
    throw new Exception('Index not found, check sphinx_xmlpipe_conf.ini');
}

$doc = new SphinxXMLFeed();


/**
 * Print schema section
 */

$doc->setFields($index_section['xmlpipe_field']);

$xml_doc_attr_list = prepare_attributes($index_section);
$doc->setAttributes($xml_doc_attr_list);

$doc->beginOutput();


/**
 * Execute pre queries and get range
 */

if (isset($index_section['sql_query_pre']) AND is_array($index_section['sql_query_pre']))
{
    $args = array('{table_prefix}' => $table_prefix);
    execute_support_queries($mysqli, $index_section['sql_query_pre'], $args, 'Error in "execute pre_query" section: %s');
}

$message_field = $index_section['message_field'];

$query = str_replace('{table_prefix}', $table_prefix, $index_section['sql_query_range']);
$mysqli_result = $mysqli->query($query);
if (!$mysqli_result)
{
    throw new Exception('Error in "get range" section:' . $mysqli->error);
}
list($first, $last) = $mysqli_result->fetch_row();
$mysqli_result->free_result();

if (!$first OR !$last)
{
    $doc->endOutput();
    die();
}


/**
 * Process and print data
 */

// prepare query template
$query_raw = str_replace('{table_prefix}', $table_prefix, trim($index_section['sql_query']));
$range_marks = array('$start', '$end');


$total_count_processed_post = 0;
$mysql_fetch_total_time = 0;
$content_processing_total_time = 0;
$xml_generate_total_time = 0;
$maxid = 0;


$start = $first;
while ($start <= $last)
{
    $time_mysql_start = microtime(true);
    $result = array();
    $content = array();


    // execute query
    $query = str_replace($range_marks, array($start, $start + $index_section['sql_range_step'] - 1), $query_raw);
    $mysqli->query($query);

    if (!$mysqli_result = $mysqli->query($query))
    {
        throw new Exception('Error in "data processing" section:' . $mysqli->error . "\n$query\n");
    }


    // fetch results
    while ($row = $mysqli_result->fetch_assoc())
    {
        $id = $row['id'];
        unset($row['id']);
        $result[$id] = $row;

        // This array is need to optimize content processing
        $contents[$id] = $row[$message_field];
    }
    $mysqli_result->free_result();


    $content_processing_time_start = microtime(true);
    $mysql_fetch_total_time += ( $content_processing_time_start - $time_mysql_start);

    if (count($result))
    {
        $total_count_processed_post += count($result);
        $contents = content_processing($contents, $replacements);
        $xml_generate_time_start = microtime(true);

        $content_processing_total_time += $xml_generate_time_start - $content_processing_time_start;


        // generate documents
        if (!isset($keys))
        {
            $keys = array_keys(current($result));
        }
        foreach ($contents as $id => $message)
        {
            $row = $result[$id];
            $row[$message_field] = $message;
            $doc->addDocument($id, $row, $keys);
        }


        // get maxid for post index queries
        $tmp_element = end($result);
        $maxid = $tmp_element['primaryid'];

        
        unset($contents, $result);
        print $doc->outputMemory();
        $xml_generate_total_time += ( microtime(true) - $xml_generate_time_start);
    }
    
    $start += $index_section['sql_range_step'];
}



/**
 * Execute sql_query_post_index
 */

if (isset($index_section['sql_query_post_index']) AND is_array($index_section['sql_query_post_index']))
{
    $args = array(
        '{table_prefix}' => $table_prefix,
        '$maxid' => $maxid,
    );
    $args['$maxid'] = $maxid;
    execute_support_queries($mysqli, $index_section['sql_query_post_index'], $args, 'Error in "sql_query_post_index" section: %s');
}


/**
 * Print kill list
 */

if (isset($index_section['sql_query_killlist']))
{
    $query = str_replace('{table_prefix}', $table_prefix, $index_section['sql_query_killlist']);
    $mysqli_result = $mysqli->query($query);
    
    if (!$mysqli_result)
    {
        throw new Exception('Error in "get kill list" section:' . $mysqli->error);
    }
    $kill_list = array();
    while ($row = $mysqli_result->fetch_row())
    {
        $kill_list[] = $row[0];
    }

    $mysqli_result->free_result();

    $doc->addKillList($kill_list);
}


/**
 * End of processing
 */

$doc->endOutput();

$script_total_time = (microtime(true) - START_TIME);

$statistic = <<< EOT
\n$index_section_name:
Fetched $total_count_processed_post docs.
Mysql fetch data total time $mysql_fetch_total_time sec.
Content processing total time $content_processing_total_time sec.
Generate xml(documents section) total time $xml_generate_total_time sec.
Script work during  $script_total_time sec.\n
EOT;

fwrite($std_err_output, $statistic);

fclose($std_err_output);


/**
 * Prepare attributes for index schema section
 *
 * @todo callback for composite types for example
 * xmlpipe_attr_uint(forum_id:9 # 9 bits for forum_id)
 *
 * @param string $type
 * @param array $values
 * @return array
 */
function get_attrs($type, $values)
{
    foreach ($values as $name)
    {
        $elements[] = array('name' => $name, 'type' => $type);
    }
    return $elements;
}


/**
 * Get db connection link
 *
 * @staticvar mysqli $link
 * @param array $db_conf
 * @return mysqli
 */
function get_link($db_conf)
{
    static $link;
    if (($link instanceof mysqli) AND !$link->errno())
    {
        return $link;
    }

    for ($i = 0; $i < 3; $i++)
    {
        // ToDo optimize
        $link = new mysqli($db_conf['host'],
                $db_conf['user'],
                $db_conf['pass'],
                $db_conf['schema'],
                $db_conf['port'],
                $db_conf['socket']);
        if (!mysqli_connect_error ())
        {
            return $link;
        }
    }
    throw new Exception("Connect failed: %s\n", mysqli_connect_error());
}


/**
 * Execute additional query such as pre|post index queries
 *
 * @param mysqli $mysqli
 * @param array $queries
 * @param array $args
 * @param string $err_msg
 * @return bool
 */
function execute_support_queries($mysqli, $queries, $args = NULL, $err_msg = '')
{
    foreach ($queries as $query)
    {
        $query = trim($query);
        if (empty($query))
        {
            continue;
        }
        if (!empty($args))
        {
            $query = str_ireplace(array_keys($args), $args, $query);
        }
        if (!$mysqli->query($query))
        {
            if (!empty($err_msg))
            {
                $err_msg = sprintf($err_msg, $mysqli->error) . "\n$query\n";
            }
            else
            {
                $err_msg = $mysqli->error;
            }
            throw new Exception($err_msg);
        }
    }
    return true;
}


/**
 * Get statements from config for specific index. Inheritance supported.
 *
 * @param array $config
 * @param string $index_section_name
 * @return array
 */
function prepare_index_section($config, $index_section_name)
{
    if (!isset($config[$index_section_name]))
    {
        throw new Exception('Invalid index name ' . $index_section_name);
    }
    $parent_section = array();
    if (isset($config[$index_section_name]['parent_index']))
    {
        $parent_section = prepare_index_section($config, $config[$index_section_name]['parent_index']);
    }
    return array_merge($parent_section, $config[$index_section_name]);
}


/**
 * prepare attrebutes for index schema xml
 *
 * @param array $index_section
 * @return array
 */
function prepare_attributes($index_section)
{
    // next code need because xmlpipe_attr_uint can't simple maped as other attrebutes
    // $attr_map has type in output xml as key and name of attribute from config as value
    $attr_map = array(
        'int' => 'xmlpipe_attr_uint',
        'timestamp' => 'xmlpipe_attr_timestamp',
        'bool' => 'xmlpipe_attr_bool',
        'str2ordinal' => 'xmlpipe_attr_str2ordinal',
        'float' => 'xmlpipe_attr_float',
    );
    $xml_doc_attr_list = array();
    foreach ($attr_map as $attr_type => $key_in_config)
    {
        if (isset($index_section[$key_in_config]))
        {
            $xml_doc_attr_list = array_merge($xml_doc_attr_list, get_attrs($attr_type, $index_section[$key_in_config]));
        }
    }
    return $xml_doc_attr_list;
}


/**
 * Group operations for cleaning content
 *
 * @param array $contents
 * @param array $replacements
 * @return array of string
 */
function content_processing($contents, $replacements)
{
    $contents = str_replace($replacements['simple_delete'], '', $contents);
    $contents = preg_replace($replacements['preg_delete'], '', $contents);
    return $contents;
}


/**
 *  SphinxXMLFeed - efficiently generate XML for Sphinx's xmlpipe2 data adapter
 *  Class based on Jetpack class from  article
 * @link http://jetpackweb.com/blog/2009/08/16/sphinx-xmlpipe2-in-php-part-ii/
 */
class SphinxXMLFeed extends XMLWriter
{

    private $fields = array();
    private $attributes = array();
    protected $kill_list = array();

    public function __construct($options = array())
    {
        $defaults = array(
            'indent' => false,
        );
        $options = array_merge($defaults, $options);

        
        // Store the xml tree in memory
        $this->openMemory();

        if ($options['indent'])
        {
            $this->setIndent(true);
        }
    }

    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function addKillList($kill_list)
    {
        $this->kill_list = $kill_list;
    }

    public function addDocument($id, $doc, $keys)
    {
        $this->startElement('sphinx:document');
        $this->writeAttribute('id', $id);

        array_map(array($this, 'writeElement'), $keys, $doc);

        $this->endElement();
    }

    public function beginOutput()
    {
        $this->startDocument('1.0', 'UTF-8');
        $this->startElement('sphinx:docset');
        $this->startElement('sphinx:schema');


        // add fields to the schema
        foreach ($this->fields as $field)
        {
            $this->startElement('sphinx:field');
            $this->writeAttribute('name', $field);
            $this->endElement();
        }


        // add attributes to the schema
        foreach ($this->attributes as $attributes)
        {
            $this->startElement('sphinx:attr');
            foreach ($attributes as $key => $value)
            {
                $this->writeAttribute($key, $value);
            }
            $this->endElement();
        }


        // end sphinx:schema
        $this->endElement();
        print $this->outputMemory();
    }

    public function endOutput()
    {
        // add kill list
        if (!empty($this->kill_list))
        {

            $this->startElement('sphinx:killlist');
            foreach ($this->kill_list as $id)
            {
                $this->writeElement("id", $id);
            }
            $this->endElement();
        }


        // end sphinx:docset
        $this->endElement();
        print $this->outputMemory();
    }

}



function exception_handler($exception)
{
    global $std_err_output;
    fwrite($std_err_output, $exception->getMessage() . "\n");
}


function error_handler($errno, $errstr, $errfile, $errline)
{
    global $std_err_output;
    fwrite($std_err_output, $errstr . "\n");
}
