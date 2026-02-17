<?php

use Alxarafe\Lib\Router;

// Chascarrillo Routes
Router::add('home', '/', 'Chascarrillo.Blog.index');
Router::add('blog_index', '/blog', 'Chascarrillo.Blog.index');
Router::add('blog_show', '/blog/{slug}', 'Chascarrillo.Blog.show');
Router::add('page_show', '/{slug}', 'Chascarrillo.Page.show');

// Admin routes (Standard)
// Router::add('admin_dashboard', '/admin', 'Admin.Dashboard.index');
