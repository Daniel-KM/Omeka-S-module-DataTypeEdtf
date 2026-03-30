<?php declare(strict_types=1);

namespace DataTypeEdtf\DataType;

use Omeka\DataType\ConversionTargetInterface;

/**
 * EDTF data type with ConversionTargetInterface (Omeka S 4.2+).
 *
 * This subclass only adds the interface declaration. The convert()  method is
 * defined in the parent class. The config swaps the invokable class when the
 * interface is available, so the module remains compatible with old Omeka S.
 *
 * @see \DataTypeEdtf\Module::getConfig()
 */
class EdtfConvertible extends Edtf implements ConversionTargetInterface
{
}
