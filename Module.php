<?php declare(strict_types=1);

namespace DataTypeEdtf;

// Load the module dependencies when installed as a zip.
// With composer, libraries are stored in omeka vendor/ and the module has none.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Common may be installed but not registered in autoloader, in particular
// during upgrade. So dynamically register all classes of the module.
if (!defined('COMMON_PSR4_FALLBACK')) {
    foreach ([
        OMEKA_PATH . '/modules/Common/src',
        OMEKA_PATH . '/composer-addons/modules/Common/src',
        dirname(__DIR__) . '/Common/src',
    ] as $commonSrc) {
        if (file_exists($commonSrc . '/TraitModule.php')) {
            define('COMMON_PSR4_FALLBACK', $commonSrc);
            spl_autoload_register(static function ($class): void {
                if (str_starts_with($class, 'Common\\')) {
                    $file = COMMON_PSR4_FALLBACK . '/' . strtr(substr($class, 7), '\\', '/') . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
            break;
        }
    }
}

use Common\TraitModule;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Events as DoctrineEvents;
use DataTypeEdtf\Db\Event\Listener\CascadeDetach;
use DataTypeEdtf\Form\Element\ConvertToEdtf;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

/**
 * Data Type Edtf.
 *
 * @copyright Omeka Team, 2018-2023
 * @copyright Steve Ranford, University of Warwick, 2023
 * @copyright Daniel Berthereau, 2017-2026
 * @license GPL3.0
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.91')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.91'
                );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        if ($services->get('Omeka\ModuleManager')->getModule('RdfDatatype')) {
            require_once __DIR__ . '/data/scripts/upgrade_from_rdfdatatype.php';
        }
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $em->getEventManager()->addEventListener(
            DoctrineEvents::preFlush,
            new CascadeDetach
        );
    }

    public function getConfigForm(\Laminas\View\Renderer\PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $legacy = $moduleManager->getModule('EdtfDataType');
        $legacyInstalled = $legacy
            && $legacy->getState() !== \Omeka\Module\Manager::STATE_NOT_FOUND
            && $legacy->getState() !== \Omeka\Module\Manager::STATE_NOT_INSTALLED;

        $legacyActive = false;
        $legacyCount = 0;
        if ($legacyInstalled) {
            try {
                $legacyCount = (int) $services->get('Omeka\Connection')
                    ->fetchOne('SELECT COUNT(*) FROM edtf_data_type_edtf');
                $legacyActive = true;
            } catch (\Throwable $e) {
                // Table does not exist.
            }
        }

        return $renderer->partial('data-type-edtf/config-form', [
            'legacyActive' => $legacyActive,
            'legacyCount' => $legacyCount,
        ]);
    }

    public function handleConfigForm(\Laminas\Mvc\Controller\AbstractController $controller)
    {
        $params = $controller->params()->fromPost();

        if (!empty($params['migrate_from_legacy'])) {
            $services = $this->getServiceLocator();
            $moduleManager = $services->get('Omeka\ModuleManager');
            $legacy = $moduleManager->getModule('EdtfDataType');
            if (!$legacy
                || $legacy->getState() === \Omeka\Module\Manager::STATE_NOT_FOUND
                || $legacy->getState() === \Omeka\Module\Manager::STATE_NOT_INSTALLED
            ) {
                $controller->messenger()->addError('The legacy module "EdtfDataType" is not installed; nothing to migrate.'); // @translate
                return false;
            }

            $dispatcher = $services->get('Omeka\Job\Dispatcher');
            $job = $dispatcher->dispatch(\DataTypeEdtf\Job\MigrateFromLegacy::class);
            if ($job) {
                $urlPlugin = $controller->url();
                $message = new \Common\Stdlib\PsrMessage(
                    'Migrating legacy "EdtfDataType" data in background (job {link_job}#{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
                    [
                        'link_job' => sprintf('<a href="%s">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                        'job_id' => $job->getId(),
                        'link_end' => '</a>',
                        'link_log' => class_exists('Log\Module', false)
                            ? sprintf('<a href="%1$s">', htmlspecialchars($urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])))
                            : sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">', htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))),
                    ]
                );
                $message->setEscapeHtml(false);
                $controller->messenger()->addSuccess($message);
            }
        }

        return $this->handleConfigFormAuto($controller);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $adapterIds = [
            'Omeka\Api\Adapter\ItemAdapter',
            'Omeka\Api\Adapter\ItemSetAdapter',
            'Omeka\Api\Adapter\MediaAdapter',
            'Omeka\Api\Adapter\ValueAnnotationAdapter',
            // Optional: Annotate module.
            'Annotate\Api\Adapter\AnnotationAdapter',
            // Optional: DigitalObject module.
            'DigitalObject\Api\Adapter\DigitalObjectAdapter',
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
                function (Event $event): void {
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
            function (Event $event): void {
                $sortings = $this->getSortings('Omeka\Entity\ItemSet');
                $sortConfig = $event->getParam('sortConfig') ?: [];
                $sortConfig = array_merge($sortConfig, $sortings);
                $event->setParam('sortConfig', $sortConfig);
            }
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.sort-selector',
            function (Event $event): void {
                $sortings = $this->getSortings('Omeka\Entity\Media');
                $sortConfig = $event->getParam('sortConfig') ?: [];
                $sortConfig = array_merge($sortConfig, $sortings);
                $event->setParam('sortConfig', $sortConfig);
            }
        );
        $sharedEventManager->attach(
            'DigitalObject\Controller\Admin\DigitalObject',
            'view.sort-selector',
            function (Event $event): void {
                $sortings = $this->getSortings('DigitalObject\Entity\DigitalObject');
                $sortConfig = $event->getParam('sortConfig') ?: [];
                $sortConfig = array_merge($sortConfig, $sortings);
                $event->setParam('sortConfig', $sortConfig);
            }
        );

        $resourceAdapterIds = [
            'Omeka\Api\Adapter\ItemAdapter',
            'Omeka\Api\Adapter\ItemSetAdapter',
            'Omeka\Api\Adapter\MediaAdapter',
            'Annotate\Api\Adapter\AnnotationAdapter',
            'DigitalObject\Api\Adapter\DigitalObjectAdapter',
        ];
        foreach ($resourceAdapterIds as $adapterId) {
            foreach (['api.create.post', 'api.update.post', 'api.delete.post'] as $eventName) {
                $sharedEventManager->attach($adapterId, $eventName, [$this, 'invalidateSortingsCache']);
            }
        }

        $searchControllerIds = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            'DigitalObject\Controller\Admin\DigitalObject',
            'Omeka\Controller\Site\Item',
        ];
        foreach ($searchControllerIds as $controllerId) {
            $sharedEventManager->attach(
                $controllerId,
                'view.advanced_search',
                function (Event $event): void {
                    $partials = $event->getParam('partials');
                    $partials[] = 'common/data-type-edtf-advanced-search';
                    $event->setParam('partials', $partials);
                }
            );
        }

        // Add humanizer style select to general settings form.
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );

        // Add JS to FacetedBrowse category form.
        $sharedEventManager->attach(
            'FacetedBrowse\Controller\SiteAdmin\Category',
            'view.faceted_browse.category_form',
            function (Event $event): void {
                $view = $event->getTarget();
                $view->headScript()->appendFile($view->assetUrl('js/faceted-browse/category-form.js', 'DataTypeEdtf'));
            }
        );

        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_elements',
            function (Event $event): void {
                $form = $event->getTarget();
                $form->add([
                    'type' => ConvertToEdtf::class,
                    'name' => 'edtf_convert',
                ]);
            }
        );
        $batchAdapterIds = [
            'Omeka\Api\Adapter\ItemAdapter',
            'Omeka\Api\Adapter\ItemSetAdapter',
            'Omeka\Api\Adapter\MediaAdapter',
            'DigitalObject\Api\Adapter\DigitalObjectAdapter',
        ];
        foreach ($batchAdapterIds as $batchAdapterId) {
            $sharedEventManager->attach(
                $batchAdapterId,
                'api.preprocess_batch_update',
                function (Event $event): void {
                    $data = $event->getParam('data');
                    $rawData = $event->getParam('request')->getContent();
                    if ($this->convertToEdtfDataIsValid($rawData)) {
                        $data['edtf_convert'] = $rawData['edtf_convert'];
                    }
                    $event->setParam('data', $data);
                }
            );
        }
    }

    /**
     * Convert property values to the specified EDTF data type.
     *
     * This will work for Item, ItemSet, Media and DigitalObject resources.
     *
     * @param Event $event
     */
    public function convertToEdtf(Event $event): void
    {
        $entity = $event->getParam('entity');
        if ($entity instanceof \Omeka\Entity\Item) {
            $resource = 'items';
        } elseif ($entity instanceof \Omeka\Entity\ItemSet) {
            $resource = 'item_sets';
        } elseif ($entity instanceof \Omeka\Entity\Media) {
            $resource = 'media';
        } elseif (class_exists('DigitalObject\Module', false)
            && $entity instanceof \DigitalObject\Entity\DigitalObject
        ) {
            $resource = 'digital_objects';
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

        $this->convertValuesToEdtf($entity, $property, $type, $dataType, $adapter, $logger);
    }

    /**
     * Convert literal values of the given property on a resource to EDTF.
     *
     * Also traverses value annotations nested inside each value.
     */
    protected function convertValuesToEdtf(
        \Omeka\Entity\Resource $entity,
        \Omeka\Entity\Property $property,
        string $type,
        $dataType,
        $adapter,
        $logger
    ): void {
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
                    'DataTypeEdtf - invalid %1$s value for ID %2$s - %3$s', // @translate
                    $type, $entity->getId(), $value->getValue()
                );
                $logger->notice($message);
            }
            // Recurse into the value annotation, if any.
            $valueAnnotation = $value->getValueAnnotation();
            if ($valueAnnotation) {
                $this->convertValuesToEdtf($valueAnnotation, $property, $type, $dataType, $adapter, $logger);
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
    public function saveEdtfData(Event $event): void
    {
        $entity = $event->getParam('entity');

        if (!$entity instanceof \Omeka\Entity\Resource) {
            // This is not a resource entity.
            return;
        }

        $allValues = $entity->getValues();

        foreach ($this->getDataTypeEdtfs() as $dataTypeName => $dataType) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('type', $dataTypeName));

            $matchingValues = $allValues->matching($criteria);

            if ($matchingValues->isEmpty()) {
                // This resource has no EDTF values of this type, skip
                // the DB query that would reload existing rows.
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
    public function buildQueries(Event $event): void
    {
        $query = $event->getParam('request')->getContent();
        if (!isset($query['edtf'])) {
            return;
        }
        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        foreach ($this->getDataTypeEdtfs() as $dataType) {
            $dataType->buildQuery($adapter, $qb, $query);
        }
    }

    /**
     * Sort EDTF queries.
     *
     * sort_by=edtf:<propertyId>
     *
     * @param Event $event
     */
    public function sortQueries(Event $event): void
    {
        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        $query = $event->getParam('request')->getContent();

        if (!isset($query['sort_by']) || !is_string($query['sort_by'])) {
            return;
        }
        $sortBy = explode(':', $query['sort_by']);
        if (2 !== count($sortBy)) {
            return;
        }
        [$namespace, $propertyId] = $sortBy;
        if ('edtf' !== $namespace || !is_numeric($propertyId)) {
            return;
        }
        foreach ($this->getDataTypeEdtfs() as $dataType) {
            $dataType->sortQuery($adapter, $qb, $query, 'edtf', $propertyId);
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
        static $memo = [];
        if (isset($memo[$instanceOf])) {
            return $memo[$instanceOf];
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $shortNames = [
            'Omeka\Entity\Item' => 'items',
            'Omeka\Entity\ItemSet' => 'item_sets',
            'Omeka\Entity\Media' => 'media',
            'Annotate\Entity\Annotation' => 'annotations',
            'DigitalObject\Entity\DigitalObject' => 'digital_objects',
        ];
        $short = $shortNames[$instanceOf] ?? null;
        if (!$short) {
            return $memo[$instanceOf] = [];
        }
        $cacheKey = 'datatypeedtf_sortings_' . $short;
        $cached = $settings->get($cacheKey);
        if (is_array($cached)) {
            return $memo[$instanceOf] = $cached;
        }

        $entityManager = $services->get('Omeka\EntityManager');
        $translator = $services->get('MvcTranslator');

        $edtfDataTypes = $this->getDataTypeEdtfs();
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
        asort($sortings);

        $settings->set($cacheKey, $sortings);
        return $memo[$instanceOf] = $sortings;
    }

    /**
     * Invalidate the sortings cache when a resource is created, updated or
     * deleted. The cache is rebuilt lazily on next access.
     */
    public function invalidateSortingsCache(Event $event): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        foreach (['items', 'item_sets', 'media', 'annotations', 'digital_objects'] as $short) {
            $settings->delete('datatypeedtf_sortings_' . $short);
        }
    }

    /**
     * Get all data types added by this module.
     *
     * @return array
     */
    public function getDataTypeEdtfs()
    {
        $dataType = $this->getServiceLocator()->get('Omeka\DataTypeManager');
        return [
            'edtf' => $dataType->get('edtf'),
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
        $validTypes = array_keys($this->getDataTypeEdtfs());
        return (
            isset($data['edtf_convert']['property'])
            && is_numeric($data['edtf_convert']['property'])
            && isset($data['edtf_convert']['type'])
            && in_array($data['edtf_convert']['type'], $validTypes)
        );
    }

}
