#!/bin/sh
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ForumMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php ThreadPostMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php DiscussionMessageMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php SocialGroupMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php VisitorMessageMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogEntryMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php BlogCommentMain --rotate
/usr/local/bin/indexer --config /usr/local/etc/sphinx-conf-dev.php CMSArticlesMain --rotate
