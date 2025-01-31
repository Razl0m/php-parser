<?php

use phpmock\MockBuilder;
use phpmock\Mock;

function mock_function($namespace, $name, $function): Mock
{
    $builder = new MockBuilder();
    $builder->setNamespace($namespace)
        ->setName($name)
        ->setFunction($function);

    $mock = $builder->build();
    $mock->enable();

    return $mock;
}