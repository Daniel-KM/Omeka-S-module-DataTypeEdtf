<?php
namespace EdtfDataType;

use Composer\Semver\Comparator;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Events as DoctrineEvents;
use EdtfDataType\Db\Event\Listener\CascadeDetach;
use EdtfDataType\Form\Element\ConvertToEdtf;
use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\ModuleManager\ModuleManager;

class Module extends AbstractModule
{

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $em->getEventManager()->addEventListener(
            DoctrineEvents::preFlush,
            new CascadeDetach
        );
    }

    public function install(ServiceLocatorInterface $services)
    {
        $conn = $services->get('Omeka\Connection');
        $conn->exec('CREATE TABLE edtf_data_type_edtf (id INT AUTO_INCREMENT NOT NULL, resource_id INT NOT NULL, property_id INT NOT NULL, value VARCHAR(255) NOT NULL, INDEX IDX_C0EBD47889329D25 (resource_id), INDEX IDX_C0EBD478549213EC (property_id), INDEX property_value (property_id, value), INDEX value (value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;');
        $conn->exec('ALTER TABLE edtf_data_type_edtf ADD CONSTRAINT FK_C0EBD47889329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;');
        $conn->exec('ALTER TABLE edtf_data_type_edtf ADD CONSTRAINT FK_C0EBD478549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE;');
    }

    public function uninstall(ServiceLocatorInterface $services)
    {
        $conn = $services->get('Omeka\Connection');
        $conn->exec('DROP TABLE IF EXISTS edtf_data_type_edtf;');
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $adapterIds = [
            'Omeka\Api\Adapter\ItemAdapter',
            'Omeka\Api\Adapter\ItemSetAdapter',
            'Omeka\Api\Adapter\MediaAdapter',
        ];
        foreach ($adapterIds as $adapterId) {
            $sharedEventManager->attach(
                $adapterId,
                'api.search.query',
                [$this, 'buildQueries']
            );
            $sharedEventManager->attach(
                $adapterId,
                'api.search.query',
                [$this, 'sortQueries']
            );
            $sharedEventManager->attach(
                $adapterId,
                'api.hydrate.post',
                [$this, 'convertToEdtf'],
                // Set a high priority so this runs before saveEdtfData().
                100
            );
            $sharedEventManager->attach(
                $adapterId,
                'api.hydrate.post',
                [$this, 'saveEdtfData']
            );
        }

        $controllerIds = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Site\Item',
        ];
        foreach ($controllerIds as $controllerId) {
            $sharedEventManager->attach(
                $controllerId,
                'view.sort-selector',
                function (Event $event) {
                    $sortings = $this->getSortings('Omeka\Entity\Item');
                    $sortConfig = $event->getParam('sortConfig') ?: [];
                    $sortConfig = array_merge($sortConfig, $sortings);
                    $event->setParam('sortConfig', $sortConfig);
                }
            );
        }
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.sort-selector',
            function (Event $event) {
                $sortings = $this->getSortings('Omeka\Entity\ItemSet');
                $sortConfig = $event->getParam('sortConfig') ?: [];
                $sortConfig = array_merge($sortConfig, $sortings);
                $event->setParam('sortConfig', $sortConfig);
            }
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.sort-selector',
            function (Event $event) {
                $sortings = $this->getSortings('Omeka\Entity\Media');
                $sortConfig = $event->getParam('sortConfig') ?: [];
                $sortConfig = array_merge($sortConfig, $sortings);
                $event->setParam('sortConfig', $sortConfig);
            }
        );

        $searchControllerIds = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            'Omeka\Controller\Site\Item',
        ];
        foreach ($searchControllerIds as $controllerId) {
            $sharedEventManager->attach(
                $controllerId,
                'view.advanced_search',
                function (Event $event) {
                    $partials = $event->getParam('partials');
                    $partials[] = 'common/edtf-data-type-advanced-search';
                    $event->setParam('partials', $partials);
                }
            );
        }

        // Add JS to FacetedBrowse category form.
        $sharedEventManager->attach(
            'FacetedBrowse\Controller\SiteAdmin\Category',
            'view.faceted_browse.category_form',
            function (Event $event) {
                $view = $event->getTarget();
                $view->headScript()->appendFile($view->assetUrl('js/faceted-browse/category-form.js', 'EdtfDataType'));
            }
        );

        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_elements',
            function (Event $event) {
                $form = $event->getTarget();
                $form->add([
                    'type' => ConvertToEdtf::class,
                    'name' => 'edtf_convert',
                ]);
            }
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.preprocess_batch_update',
            function (Event $event) {
                $data = $event->getParam('data');
                $rawData = $event->getParam('request')->getContent();
                if ($this->convertToEdtfDataIsValid($rawData)) {
                    $data['edtf_convert'] = $rawData['edtf_convert'];
                }
                $event->setParam('data', $data);
            }
        );
    }

    /**
     * Convert property values to the specified EDTF data type.
     *
     * This will work for Item, ItemSet, and Media resources.
     *
     * @param Event $event
     */
    public function convertToEdtf(Event $event)
    {
        $entity = $event->getParam('entity');
        if ($entity instanceof \Omeka\Entity\Item) {
            $resource = 'items';
        } elseif ($entity instanceof \Omeka\Entity\ItemSet) {
            $resource = 'item_sets';
        } elseif ($entity instanceof \Omeka\Entity\Media) {
            $resource = 'media';
        } else {
            // This is not a resource entity.
            return;
        }

        $data = $event->getParam('request')->getContent();
        if (!$this->convertToEdtfDataIsValid($data)) {
            // This is not a convert-to-edtf request.
            return;
        }

        $propertyId = (int) $data['edtf_convert']['property'];
        $type = $data['edtf_convert']['type'];

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $dataType = $services->get('Omeka\DataTypeManager')->get($type);
        $adapter = $services->get('Omeka\ApiAdapterManager')->get($resource);
        $logger = $services->get('Omeka\Logger');

        $dql = 'SELECT p FROM Omeka\Entity\Property p WHERE p.id = :id';
        $property = $entityManager->createQuery($dql)
            ->setParameter('id', $propertyId)
            ->getOneOrNullResult();
        if (null === $property) {
            // The property doesn't exist. Do nothing.
            return;
        }

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('property', $property))
            ->andWhere(Criteria::expr()->eq('type', 'literal'));
        $values = $entity->getValues()->matching($criteria);
        foreach ($values as $value) {
            $valueObject = ['@value' => $value->getValue()];
            if ($dataType->isValid($valueObject)) {
                $value->setType($type);
                $dataType->hydrate($valueObject, $value, $adapter);
            } else {
                $message = sprintf(
                    'EdtfDataType - invalid %s value for ID %s - %s', // @translate
                    $type, $entity->getId(), $value->getValue()
                );
                $logger->notice($message);
            }
        }
    }

    /**
     * Save EDTF data to the corresponding entity tables.
     *
     * This clears all existing entries and (re)saves them during create and
     * update operations for a resource (item, item set, media). We do this
     * as an easy way to ensure that the entries in the entity tables are in
     * sync with the values in the value table.
     *
     * @param Event $event
     */
    public function saveEdtfData(Event $event)
    {
        $entity = $event->getParam('entity');

        if (!$entity instanceof \Omeka\Entity\Resource) {
            // This is not a resource entity.
            return;
        }

        $allValues = $entity->getValues();

        foreach ($this->getEdtfDataTypes() as $dataTypeName => $dataType) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('type', $dataTypeName));

            $matchingValues = $allValues->matching($criteria);

            if (!$matchingValues) {
                // This resource has no number values of this type.
                continue;
            }

            $em = $this->getServiceLocator()->get('Omeka\EntityManager');
            $existingNumbers = [];

            if ($entity->getId()) {
                $dql = sprintf(
                    'SELECT n FROM %s n WHERE n.resource = :resource',
                    $dataType->getEntityClass()
                );
                $query = $em->createQuery($dql);
                $query->setParameter('resource', $entity);
                $existingNumbers = $query->getResult();
            }
            foreach ($matchingValues as $value) {
                // Avoid ID churn by reusing number rows.
                $number = current($existingNumbers);
                if ($number === false) {
                    // No more number rows to reuse. Create a new one.
                    $entityClass = $dataType->getEntityClass();
                    $number = new $entityClass;
                    $em->persist($number);
                } else {
                    // Null out numbers as we reuse them. Note that existing
                    // numbers are already managed and will update during flush.
                    $existingNumbers[key($existingNumbers)] = null;
                    next($existingNumbers);
                }
                $number->setResource($entity);
                $number->setProperty($value->getProperty());
                $dataType->setEntityValues($number, $value);
            }
            // Remove any numbers that weren't reused.
            foreach ($existingNumbers as $existingNumber) {
                if (null !== $existingNumber) {
                    $em->remove($existingNumber);
                }
            }
        }
    }

    /**
     * Build EDTF queries.
     *
     * @param Event $event
     */
    public function buildQueries(Event $event)
    {
        $query = $event->getParam('request')->getContent();
        if (!isset($query['edtf'])) {
            return;
        }
        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        foreach ($this->getEdtfDataTypes() as $dataType) {
            $dataType->buildQuery($adapter, $qb, $query);
        }
    }

    /**
     * Sort EDTF queries.
     *
     * sort_by=edtf:<type>:<propertyId>
     *
     * @param Event $event
     */
    public function sortQueries(Event $event)
    {
        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        $query = $event->getParam('request')->getContent();

        if (!isset($query['sort_by']) || !is_string($query['sort_by'])) {
            return;
        }
        $sortBy = explode(':', $query['sort_by']);
        if (3 !== count($sortBy)) {
            return;
        }
        [$namespace, $type, $propertyId] = $sortBy;
        if ('edtf' !== $namespace || !is_string($type) || !is_numeric($propertyId)) {
            return;
        }
        foreach ($this->getEdtfDataTypes() as $dataType) {
            $dataType->sortQuery($adapter, $qb, $query, $type, $propertyId);
        }
    }

    /**
     * Get EDTF sort options for sort by form.
     *
     * @param string $instanceOf
     * @return array
     */
    public function getSortings($instanceOf)
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $translator = $services->get('MvcTranslator');

        $edtfDataTypes = $this->getEdtfDataTypes();
        $sortings = [];
        foreach ($edtfDataTypes as $edtfDataType) {
            $dql = sprintf(<<<'SQL'
                SELECT DISTINCT property.id, property.label
                FROM %s ndt
                JOIN ndt.property property
                JOIN ndt.resource resource
                WHERE resource INSTANCE OF %s
                SQL,
                $edtfDataType->getEntityClass(),
                $instanceOf
            );
            $query = $entityManager->createQuery($dql);
            $properties = $query->getResult();
            foreach ($properties as $property) {
                $sortingKey = sprintf('%s:%s', $edtfDataType->getName(), $property['id']);
                $sortingValue = sprintf('%s (%s)', $translator->translate($property['label']), $edtfDataType->getName());
                $sortings[$sortingKey] = $sortingValue;
            }
        }
        // Sort options alphabetically.
        asort($sortings);
        return $sortings;
    }

    /**
     * Get all data types added by this module.
     *
     * @return array
     */
    public function getEdtfDataTypes()
    {
        $dataType = $this->getServiceLocator()->get('Omeka\DataTypeManager');
        return [
            'edtf:date' => $dataType->get('edtf:date'),
        ];
    }

    /**
     * Does the passed data contain valid convert-to-edtf data?
     *
     * @param array $data
     * @return bool
     */
    public function convertToEdtfDataIsValid(array $data)
    {
        $validTypes = array_keys($this->getEdtfDataTypes());
        return (
            isset($data['edtf_convert']['property'])
            && is_numeric($data['edtf_convert']['property'])
            && isset($data['edtf_convert']['type'])
            && in_array($data['edtf_convert']['type'], $validTypes)
        );
    }
}
