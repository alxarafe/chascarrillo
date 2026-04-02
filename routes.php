<?php

use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Infrastructure\Http\Router;

// Alxarafe Configuration Extensions for Chascarrillo
Config::registerSection('blog', ['title', 'posts_per_page', 'excerpt_length']);
Config::registerSection('social', ['twitter', 'instagram', 'facebook']);

// Initialize Domain Container
\Modules\Chascarrillo\Application\AppContainer::get();

// Chascarrillo Routes
$config = \Alxarafe\Infrastructure\Persistence\Config::getConfig();
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
