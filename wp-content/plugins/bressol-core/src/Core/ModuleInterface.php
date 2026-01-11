<?php
declare(strict_types=1);

namespace Bressol\Core;

if (!defined('ABSPATH')) {
    exit;
}

interface ModuleInterface
{
    public function register(): void;
}