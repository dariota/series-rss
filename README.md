# Requirements

Apache2 + php7.4 + sqlite (libsqlite >= 3.7.4, via php-sqlite3 on Ubuntu) + libcurl >= 7.15.5 (via php-curl on Ubuntu)

## Set up Auth for submission
Enable AllowOverride AuthConfig in your apache config (probably `/etc/apache2/apache2.conf`)
Enable the apache group authorisation module `a2enmod authz_groupfile`

`mkdir -p /usr/local/apache/passwd`
`htpasswd -c -B -C 10 /usr/local/apache/passwd/passwords username`
`echo "SeriesSubmission: username" > /usr/local/apache/passwd/groups`

Ensure the file is accessible to the user/group apache runs as (probably www-data)

Create a password file in /var/www/passwords accessible to the group apache runs as using `htpasswd -c -B -C 10 /var/www/passwords`

## Set up OMDB Auth

Get an API key for [OMDB](http://www.omdbapi.com/), create a JSON file containing it as shown in the sample `seriesRssSecrets.json.sample`. Put that file in the `/usr/local/apache` directory created for submission auth above.
