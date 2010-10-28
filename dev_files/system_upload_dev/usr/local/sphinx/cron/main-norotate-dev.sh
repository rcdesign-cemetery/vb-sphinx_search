#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ForumMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ThreadPostMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php DiscussionMessageMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php SocialGroupMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php VisitorMessageMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogEntryMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogCommentMain
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php CMSArticlesMain
