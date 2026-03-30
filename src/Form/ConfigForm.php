<?php declare(strict_types=1);

namespace DataTypeEdtf\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this->add([
            'name' => 'migrate_from_legacy',
            'type' => Element\Hidden::class,
            'attributes' => [
                'id' => 'migrate_from_legacy',
                'value' => '',
            ],
        ]);
    }
}
