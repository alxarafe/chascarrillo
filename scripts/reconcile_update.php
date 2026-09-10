#!/usr/bin/env php
<?php

declare(strict_types=1);

use Modules\Chascarrillo\Service\UpdateReconciliationCommand;

require dirname(__DIR__) . '/vendor/autoload.php';

exit((new UpdateReconciliationCommand(dirname(__DIR__)))->run(array_slice($argv, 1)));
