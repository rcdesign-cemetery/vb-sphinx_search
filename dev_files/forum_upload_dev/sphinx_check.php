<?php

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'sphinx_search');
define('RESULTS_LIMIT', 10);

require_once('./global.php');
//old stuff,  we should start getting rid of this
//new search stuff.
require_once(DIR . "/vb/search/core.php");
require_once(DIR . "/vb/legacy/currentuser.php");


echo '
<html>
    <head>
        <title>Чекалка запросов</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    </head>
    <body>
        <form method="POST" id="search_form" action="sphinx_check.php">
            Запрос к сфинксу<br />
            <input type="text" id="keywords" name="keywords"/><br />
            <input type="submit" />
        </form>
';

$vbulletin->input->clean_array_gpc('r', array(
    'keywords' => TYPE_STR,
));
if ($vbulletin->GPC_exists['keywords'])
{
    $keywords = $vbulletin->GPC['keywords'];
    echo '<br /><br />
Результаты для:<br />'
    . $keywords .
    '<hr />
';

    $search_core = vB_Search_Core::get_instance();

    $general_search_template = 'SELECT *, groupid * 64 + contenttypeid AS gkey FROM ThreadPostMain,ThreadPostDelta WHERE deleted = 0 AND MATCH(\'"%s"/1\') GROUP BY gkey ORDER BY @weight desc LIMIT ' . RESULTS_LIMIT . ' OPTION max_matches=' . RESULTS_LIMIT;
    $query = sprintf($general_search_template, $keywords);
    echo 'Общий запрос:<br />' . $query;
    echo '<pre>';
    _run_query($query);
    echo '<br />Обшая информация о запросе<br />';
    _run_query('SHOW META');
    echo '</pre>';

    echo '<hr />';
    $similar_threads_search_template = 'SELECT *, groupid * 64 + contenttypeid AS gkey FROM ThreadPostMain,ThreadPostDelta WHERE deleted = 0 AND isfirst = 1 AND MATCH(\'@grouptitle "%s"/1\') AND contenttypeid = 1 AND groupdateline >= 1255868521 AND groupvisible = 1 GROUP BY gkey ORDER BY @weight desc LIMIT ' . RESULTS_LIMIT . ' OPTION max_matches=' . RESULTS_LIMIT;
    $query = sprintf($similar_threads_search_template, $keywords);
    echo 'Запрос для похожих тем:<br />' . $query;
    echo '<pre>';
    _run_query($query);
    echo '<br />Обшая информация о запросе<br />';
    _run_query('SHOW META');
    echo '</pre>';
}

echo '
        </body>
</html>
';

function _run_query($query)
{
    for ($i = 0; $i < vBSphinxSearch_Core::RECONNECT_LIMIT; $i++)
    {
        $con = vBSphinxSearch_Core::get_sphinxql_conection();
        if (false != $con)
        {
            $result_res = mysql_query($query, $con);
            if ($result_res)
            {
                $table_str = '<table id="results" name="results" border="1">';
                while ($docinfo = mysql_fetch_assoc($result_res))
                {
                    // не эстетично, но быстро
                    $link = '';
                    if (isset($docinfo['primaryid']))
                    {
                        $thread['threadid'] = $docinfo['groupid'];
                        $thread['postidid'] = $docinfo['primaryid'];
                        $link = '<a href="' . fetch_seo_url('thread', $thread) . '#post' . $thread['postidid'] . '">' . $thread['postidid'] .'<a>';
                        $docinfo = array_merge(array('link'=>$link), $docinfo);
                    }
                    $head = '<tr><td>'.implode('</td><td>', array_keys($docinfo)).'</td></tr>';
                    $entry .= '<tr><td>'.implode('</td><td>', $docinfo).'</td></tr>';
                }

                $table_str .= $head . $entry . '</table>';
                echo $table_str;
                return true;
            }
            echo 'Нет результатов<br />';
        }
    }
    echo 'Ошибка: <br />' . mysql_error() . '<br />';
    return false;
}