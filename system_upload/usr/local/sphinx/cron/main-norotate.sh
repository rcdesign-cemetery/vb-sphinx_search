#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ForumMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ThreadPostMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php DiscussionMessageMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php SocialGroupMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php VisitorMessageMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogEntryMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogCommentMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php CMSArticlesMain
