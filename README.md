# Edtf Data Types

This module supports the Extended Date Time Format.

It builds on the work done by the Numeric Data Types module and incorporates EDTF functions from [ProfessionalWiki's EDTF implementation in Php](https://github.com/ProfessionalWiki/EDTF)

# TODO

- [ ] Implement `ConversionTargetInterface` (Omeka S 4.2+) on `Edtf` data type to use the native batch conversion system instead of the custom `ConvertToEdtf` mechanism.
- [ ] Adapt FacetedBrowse facet types for EDTF intervals and durations (value_greater_than, value_less_than, duration_greater_than, duration_less_than are currently disabled).

# Copyright

Specialisation of EdtfDataType is Copyright © 2023-present University of Warwick https://warwick.ac.uk/digitalhumanities.

NumericDataTypes is Copyright © 2018-present Corporation for Digital Scholarship, Vienna, Virginia, USA http://digitalscholar.org

The Corporation for Digital Scholarship distributes the Omeka source code
under the GNU General Public License, version 3 (GPLv3). The full text
of this license is given in the license file.

The Omeka name is a registered trademark of the Corporation for Digital Scholarship.

Third-party copyright in this distribution is noted where applicable.

All rights not expressly granted are reserved.
