<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024-2026 Rafael San José <rsanjose@alxarafe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Modules\Chascarrillo\Application;

use Alxarafe\Infrastructure\Container\ServiceContainer;
use Alxarafe\Domain\Port\Driven\PersistencePort;
use Alxarafe\Infrastructure\Adapter\Persistence\PdoMysqlAdapter;
use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Application\Bus\SimpleCommandBus;
use Modules\Chascarrillo\Domain\Port\Driven\PostRepositoryInterface;
use Modules\Chascarrillo\Infrastructure\Adapter\Persistence\PdoPostRepository;
use Modules\Chascarrillo\Application\Bus\Command\CreatePostCommand;
use Modules\Chascarrillo\Application\Bus\Handler\CreatePostHandler;

class AppContainer
{
    private static ?ServiceContainer $instance = null;

    public static function get(): ServiceContainer
    {
        if (self::$instance === null) {
            $container = new ServiceContainer();

            // Setup Persistence Port
            $container->singleton(PersistencePort::class, function () {
                $dbConfig = Config::getConfig()->db;
                return PdoMysqlAdapter::fromConfig($dbConfig);
            });

            // Repositories
            $container->singleton(PostRepositoryInterface::class, function ($c) {
                return new PdoPostRepository($c->get(PersistencePort::class));
            });

            // Command Bus
            $container->singleton(SimpleCommandBus::class, function ($c) {
                $bus = new SimpleCommandBus();
                $bus->registerCommand(
                    CreatePostCommand::class,
                    new CreatePostHandler($c->get(PostRepositoryInterface::class))
                );
                return $bus;
            });

            self::$instance = $container;
        }

        return self::$instance;
    }
}
