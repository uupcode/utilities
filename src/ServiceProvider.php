<?php

declare(strict_types=1);

namespace UupCode\Utilities;

use UupCode\Utilities\Attributes\Action;
use UupCode\Utilities\Attributes\Filter;

abstract class ServiceProvider
{
    public function register(): void
    {
        $ref = new \ReflectionClass($this);

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Action::class) as $attr) {
                $action = $attr->newInstance();
                Hook::action($action->hook, [$this, $method->getName()], $action->priority, $action->args);
            }

            foreach ($method->getAttributes(Filter::class) as $attr) {
                $filter = $attr->newInstance();
                Hook::filter($filter->hook, [$this, $method->getName()], $filter->priority, $filter->args);
            }
        }
    }
}
