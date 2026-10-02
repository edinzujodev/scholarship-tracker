<?php

declare(strict_types=1);

namespace Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Builds the Doctrine schema in the in-memory SQLite database before each
 * test. Every test boots a fresh application, so every test gets a clean DB.
 */
trait RefreshDoctrineSchema
{
    protected EntityManagerInterface $em;

    protected function setUpRefreshDoctrineSchema(): void
    {
        $this->em = $this->app->make(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }
}
