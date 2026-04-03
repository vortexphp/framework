<?php

declare(strict_types=1);

namespace Vortex\Database\Schema;

use Vortex\Database\Connection;

interface Migration
{
    public function id(): string;

    public function up(Connection $db): void;

    public function down(Connection $db): void;
}
