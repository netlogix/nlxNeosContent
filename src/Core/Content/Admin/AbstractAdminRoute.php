<?php declare(strict_types=1);

namespace netlogixNeosContent\Core\Content\Admin;

use Symfony\Component\HttpFoundation\Response;

abstract class AbstractAdminRoute
{
    abstract public function getDecorated(): AbstractAdminRoute;

    abstract public function load(): Response;
}
