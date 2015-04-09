# staticly

* Tags: static, html, cache
* Requires at least: 3.0.1
* Tested up to: 4.1
* Stable tag: 1.2
* License: GPLv3
* License URI: http://www.gnu.org/licenses/gpl-3.0.html

##### Generates static html files while normal loading.


Use it with following htaccess entry:

```
RewriteEngine On

RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{QUERY_STRING} ^(s=.*)$
RewriteRule ^/? index.php?%1 [R=302,L]

RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{DOCUMENT_ROOT}/static/$0/index.html -f
RewriteRule ^(.*)$ static/$1/index.html [L]

```
