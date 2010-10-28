#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ForumMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php ThreadPostMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php DiscussionMessageMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php SocialGroupMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php VisitorMessageMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogEntryMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php BlogCommentMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf.php CMSArticlesMain --rotate
