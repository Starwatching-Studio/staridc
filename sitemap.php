<?php
/**
 * 站点地图 sitemap.xml 动态生成
 * 访问 /sitemap.php 或配置服务器重写到 /sitemap.xml
 */
define('ROOT', __dirname__ . '/');
require_once ROOT . 'rd/bootstrap.php';

$base = rtrim(siteUrl(), '/');
$urls = [
    $base . '/',
    $base . '/login.php',
    $base . '/cart.php',
    $base . '/personalpanel.php',
];

try {
    $models = $DB->query("SELECT id FROM vhost_models WHERE status = 1");
    while ($m = $models->fetch()) {
        $urls[] = $base . '/index.php?model=' . intval($m['id']);
    }
} catch (Exception $e) {}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . h($u) . '</loc></url>' . "\n";
}
echo '</urlset>';
