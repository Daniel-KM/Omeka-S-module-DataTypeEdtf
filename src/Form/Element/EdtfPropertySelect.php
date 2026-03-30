<?php declare(strict_types=1);

namespace DataTypeEdtf\Form\Element;

use Doctrine\ORM\EntityManager;
use Laminas\Form\Element\Select;

class EdtfPropertySelect extends Select
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    protected $valueOptionsCache;

    /**
     * @param EntityManager $entityManager
     */
    public function setEntityManager(EntityManager $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * Get value options for template properties of EDTF data types.
     *
     * @return array
     */
    public function getValueOptions(): array
    {
        if (isset($this->valueOptionsCache)) {
            return $this->valueOptionsCache;
        }

        $dataTypes = $this->getOption('edtf_data_type');
        $disambiguate = $this->getOption('edtf_data_type_disambiguate');

        $edtfDataTypes = [];
        if (is_string($dataTypes)) {
            $edtfDataTypes['edtf:' . $dataTypes] = true;
        } elseif (is_array($dataTypes)) {
            foreach ($dataTypes as $dt) {
                $edtfDataTypes['edtf:' . $dt] = true;
            }
        } else {
            $edtfDataTypes['edtf'] = true;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('rtp')
            ->from('Omeka\Entity\ResourceTemplateProperty', 'rtp')
            ->andWhere($qb->expr()->isNotNull('rtp.dataType'));
        $query = $qb->getQuery();
        $valueOptions = [];
        foreach ($query->getResult() as $templateProperty) {
            $property = $templateProperty->getProperty();
            $template = $templateProperty->getResourceTemplate();
            foreach ($templateProperty->getDataType() ?? [] as $dataType) {
                if (!isset($edtfDataTypes[$dataType])) {
                    // This is not a requested edtf data type.
                    continue;
                }
                $value = $disambiguate
                    ? sprintf('%s:%s', $dataType, $property->getId())
                    : $property->getId();
                $label = $disambiguate
                    ? sprintf('%s (%s)', $property->getLabel(), $dataType)
                    : $property->getLabel();
                if (!isset($valueOptions[$value])) {
                    $valueOptions[$value] = [
                        'label' => $label,
                        'value' => $value,
                        'template_labels' => [],
                    ];
                }
                $templateLabel = $disambiguate
                    ? sprintf(
                        '• %s: %s',
                        $template->getLabel(),
                        $templateProperty->getAlternateLabel() ?: $property->getLabel()
                    )
                    : sprintf(
                        '• %s: %s (%s)',
                        $template->getLabel(),
                        $templateProperty->getAlternateLabel() ?: $property->getLabel(),
                        $dataType
                    );
                // More than one template could use the same property.
                $valueOptions[$value]['template_labels'][] = $templateLabel;
            }
        }

        // Include template/property labels in the option title attribute.
        foreach ($valueOptions as $value => $option) {
            $templateLabels = $option['template_labels'];
            $valueOptions[$value]['attributes']['title'] = implode("\n", $templateLabels);
        }

        usort($valueOptions, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        $this->valueOptionsCache = $valueOptions;
        return $this->valueOptionsCache;
    }
}
