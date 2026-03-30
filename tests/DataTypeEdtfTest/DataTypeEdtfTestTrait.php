<?php declare(strict_types=1);

namespace DataTypeEdtfTest;

trait DataTypeEdtfTestTrait
{
    protected function loginAdmin(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $auth = $services->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function logout(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $auth = $services->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    protected function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->getApplication()->getServiceManager()
            ->get('Omeka\Connection');
    }

    protected function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        return $this->getApplication()->getServiceManager()
            ->get('Omeka\EntityManager');
    }

    protected function getApiManager(): \Omeka\Api\Manager
    {
        return $this->getApplication()->getServiceManager()
            ->get('Omeka\ApiManager');
    }
}
