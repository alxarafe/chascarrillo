<?php

use Alxarafe\Lib\Router;

// Chascarrillo Routes
$config = \Alxarafe\Base\Config::getConfig();
$homeSlug = $config->main->homePage ?? null;

if ($homeSlug) {
    Router::add('home', '/', 'Chascarrillo.Page.show', ['slug' => $homeSlug]);
} else {
    Router::add('home', '/', 'Chascarrillo.Blog.index');
}
Router::add('blog_index', '/blog', 'Chascarrillo.Blog.index');
Router::add('blog_show', '/blog/{slug}', 'Chascarrillo.Blog.show');
Router::add('page_show', '/{slug}', 'Chascarrillo.Page.show');

// Admin routes (Standard)
// Router::add('admin_dashboard', '/admin', 'Admin.Dashboard.index');
