<?php
namespace NumericDataTypes\Db\Event\Listener;

use Doctrine\ORM\Event\PreFlushEventArgs;
use NumericDataTypes\Entity\NumericDataTypesDuration;
use NumericDataTypes\Entity\NumericDataTypesInteger;
use NumericDataTypes\Entity\NumericDataTypesInterval;
use NumericDataTypes\Entity\NumericDataTypesTimestamp;

class CascadeDetach
{
    /**
     * Simulate Doctrine's cascade detach.
     *
     * Before flushing the entity manager, this automatically detaches NDT
     * entities that reference un-managed Omeka resources. This prevents
     * Doctrine's "a new entity was found" errors, which happen when the
     * resource is detached but the NDT entity remains managed for whatever
     * reason.
     */
    public function preFlush(PreFlushEventArgs $event)
    {
        $em = $event->getEntityManager();
        $uow = $em->getUnitOfWork();
        $identityMap = $uow->getIdentityMap();
        $insertions = $uow->getScheduledEntityInsertions();

        $entityClasses = [
            NumericDataTypesDuration::class,
            NumericDataTypesInteger::class,
            NumericDataTypesInterval::class,
            NumericDataTypesTimestamp::class,
        ];
        foreach ($entityClasses as $entityClass) {
            if (isset($identityMap[$entityClass])) {
                foreach ($identityMap[$entityClass] as $entity) {
                    if (!$em->contains($entity->getResource())) {
                        $em->detach($entity);
                    }
                }
            }
            foreach ($insertions as $entity) {
                if ($entity instanceof $entityClass && !$em->contains($entity->getResource())) {
                    $em->detach($entity);
                }
            }
        }
    }
}
