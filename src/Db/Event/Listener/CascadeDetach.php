<?php declare(strict_types=1);

namespace DataTypeEdtf\Db\Event\Listener;

use Doctrine\ORM\Event\PreFlushEventArgs;
use DataTypeEdtf\Entity\Edtf;

class CascadeDetach
{
    /**
     * Simulate Doctrine's cascade detach.
     *
     * Before flushing the entity manager, this automatically detaches EDTF
     * entities that reference un-managed Omeka resources. This prevents
     * Doctrine's "a new entity was found" errors, which happen when the
     * resource is detached but the EDTF entity remains managed for
     * whatever reason.
     */
    public function preFlush(PreFlushEventArgs $event): void
    {
        $em = $event->getEntityManager();
        $uow = $em->getUnitOfWork();
        $identityMap = $uow->getIdentityMap();
        $insertions = $uow->getScheduledEntityInsertions();

        $entityClasses = [
            Edtf::class,
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
