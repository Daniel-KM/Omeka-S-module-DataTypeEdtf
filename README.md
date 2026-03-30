Extended Date/Time Format Data Type (module for Omeka S)
========================================================

[EDTF Data Type] is a module for [Omeka S] that adds a data type for the
[Extended Date/Time Format] (EDTF), allowing to describe dates and times with
precision, uncertainty, and approximation.


About EDTF
----------

The [Extended Date/Time Format] (EDTF) is a standard (ISO 8601-2:2019) for
representing dates and times that goes beyond conventional date formats. It was
developed by the Library of Congress and is widely used in cultural heritage,
archives, and digital humanities.

For the full specification, see the [Library of Congress EDTF page] or the [whole iso standard] (paywall).

Unlike simple date formats (e.g. `2024-03-15`), EDTF can express:

- Uncertain dates: `1789?` (the year 1789, but uncertain)
- Approximate dates: `1789~` (approximately 1789)
- Uncertain and approximate: `1789%` (uncertain and approximate)
- Intervals: `1964/2008` (from 1964 to 2008)
- Open intervals: `1964/..` (from 1964, ongoing)
- Reduced precision: `198X` (the 1980s), `19XX` (the 1900s or the 20th century)
- Seasons: `2001-21` (spring 2001)
- Sets: `[1667,1668,1670..1672]` (one of 1667, 1668, or 1670 to 1672)
- Unspecified digits: `156X-12-25` (December 25 of a 1560s year)
- Negative years: `Y-17E7` (170 million years ago)

Common examples in cultural heritage:

| EDTF value         | Meaning                                |
|--------------------|----------------------------------------|
| `2024-03-15`       | March 15, 2024                         |
| `2024-03`          | March 2024                             |
| `2024`             | The year 2024                          |
| `1789?`            | 1789 (uncertain)                       |
| `1789~`            | Approximately 1789                     |
| `1789%`            | 1789 (uncertain and approximate)       |
| `1900/1999`        | From 1900 to 1999                      |
| `../1871`          | Open start, until 1871                 |
| `/1871`            | Unknown start, ends at 1871            |
| `1871/..`          | From 1871, open end (ongoing)          |
| `1871/`            | From 1871, unknown end                 |
| `198X`             | The 1980s                              |
| `2001-21`          | Spring 2001                            |
| `-0489-09-07`      | 12 September 490 BCE Julian (Marathon) |

### Astronomical year numbering

EDTF inherits the proleptic Gregorian calendar with **astronomical year numbering**.
So there is a year `0000`, equal to 1 BCE.

Therefore:

- `0000` = 1 BCE
- `-0001` = 2 BCE
- `-0300` = 301 BCE
- `-0999` = 1000 BCE

References:
- ISO 8601-1:2019 §4.1.2.4 and §5.2.2;
- Library of Congress EDTF draft (2012), §4 « Date ».

### Century representation

Year ranges with reduced precision (EDTF `XX`/`XXX` unspecified digits) are
humanized using the ordinal convention:

| EDTF    | Humanized       | Period covered                      |
|---------|-----------------|-------------------------------------|
| `198X`  | 1980s           | 1980–1989                           |
| `19XX`  | 20th century    | 1900–1999                           |
| `02XX`  | 3rd century     | 0200–0299                           |
| `1XXX`  | 2nd millennium  | 1000–1999                           |
| `-03XX` | 4th century BCE | -0300 to -0399 (301 BCE to 400 BCE) |

Note that the ordinal numbering follows the common "1900s = 20th century"
convention (years ending in 0). The strict Gregorian convention
("1901–2000 = 20th century") is not used.

Nevertheless, in an historical context, you may prefer to use intervals:

| EDTF        | Humanized    | Period covered |
|-------------|--------------|----------------|
| `1601/1700` | 17th century | 1601 to 1700   |

### Half-centuries and other sub-century periods

EDTF does not define a native syntax for expressing half-centuries,
quarter-centuries or half-decades. The Level 2 sub-year groupings (codes 21–41)
replace the month position in `YYYY-MM` and therefore only split a year, not a
century.

To express for example "2nd half of the 17th century", use an explicit interval:

| Natural expression            | EDTF                                        |
|-------------------------------|---------------------------------------------|
| 2nd half of the 17th century  | `1651/1700`                                 |
| 1st half of the 17th century  | `1601/1650`                                 |
| 1st quarter of the 1800s      | `1801/1825`                                 |
| 1950s only                    | `1950/1959` or `195X`, depending on context |

The humanizer currently displays intervals as "X to Y" literally (e.g. `1651/1700`
=> "1651 to 1700").

### Allowed characters in EDTF values

| Character    | Usage                                              |
|--------------|----------------------------------------------------|
| `0`–`9`      | Year, month, day, hour, minute, second             |
| `-`          | Date separator, negative year/offset               |
| `:`          | Time separator (hours:minutes:seconds)             |
| `T`          | Date/time separator                                |
| `Z`          | UTC timezone                                       |
| `+`          | Positive timezone offset                           |
| `?`          | Uncertain                                          |
| `~`          | Approximate                                        |
| `%`          | Uncertain and approximate                          |
| `X`          | Unspecified digit (`198X`, `19XX`)                 |
| `/`          | Interval separator (start/end)                     |
| `..`         | Open start/end (extending indefinitely)            |
| `[` `]`      | Set (one of)                                       |
| `{` `}`      | Inclusive set (all members)                        |
| `,`          | Set member separator                               |
| `Y`          | Long year prefix (`Y17000`, `Y-17E7`)              |
| `E`          | Exponent for scientific notation                   |
| `S`          | Significant digits (`1950S2`)                      |
| `(` `)`      | Group qualification (level 2)                      |
| `21`–`24`    | Seasons (spring, summer, autumn, winter)           |
| `25`–`41`    | Sub-year groupings (quarter, semester, etc.)       |


Features
--------

### EDTF data type

The module adds the data type `EDTF Date/Time` (`edtf:date`) that can be
assigned to any property in a resource template. Values are validated in
real-time in the resource form, and stored in a dedicated index table for
efficient querying and sorting.

When displayed, EDTF values are humanized, for example `1789~` becomes "1789 (approximately)").

### Advanced search

EDTF fields appear in the advanced search form for items, allowing to filter
resources by:

- Date on or after: find resources with a date greater than or equal to a given
  EDTF value
- Date on or before: find resources with a date less than or equal to a given
  EDTF value

### Sorting

Resources can be sorted by any property using the EDTF data type, both in the
admin interface and on public sites.

### Batch conversion

Existing literal values can be batch-converted to EDTF. Use the batch edit
action on items: select the property and the target EDTF data type. Invalid
values are skipped and logged.

### CSV Import

The EDTF data type is registered for [CSV Import], mapped to the literal
adapter.

### Data Visualization integration

When the [Datavis] module is installed, the following are available:

- Dataset types: count items time series, count items property values time
  series
- Diagram types: line chart time series, histogram time series, line chart time
  series grouped

### Faceted Browse integration

When the [Faceted Browse] module is installed, seven facet types are available:

- Date after
- Date before
- Date in interval


Installation
------------

See general end user documentation for [installing a module].

This module requires PHP 7.4+ and the Composer dependency [professional-wiki/edtf].

* From the zip

Download the last release [DataTypeEdtf.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `DataTypeEdtf`, then install Composer dependencies:

```sh
cd modules/DataTypeEdtf
composer install --no-dev
```

The JavaScript library [EDTF.js] does not provide a browser-ready build. To
rebuild the vendor bundle file `asset/vendor/edtf/edtf.js`:

```sh
cd modules/DataTypeEdtf
npm install --no-save edtf
echo 'import { parse } from "edtf"; window.edtf = { parse };' \
    | npx esbuild --bundle --format=iife --platform=browser --outfile=asset/vendor/edtf/edtf.js
rm -rf node_modules
```

Then install it like any other Omeka module.


Usage
-----

### Setting up a property with EDTF

1. Go to Resource templates in the admin interface.
2. Edit or create a template.
3. For the desired property (e.g. `dcterms:date`), select `EDTF Date/Time` as
   the data type.
4. Save the template.

When editing a resource using this template, the property field will validate
EDTF input in real-time and display any syntax errors.

### Searching by EDTF values

In the advanced search form for items, EDTF filter fields allow to search for
resources with dates on or after and/or on or before a given value.

Via the API, use the `edtf` query parameter:

```
/api/items?edtf[date][gte][<propertyId>]=1900&edtf[date][lte][<propertyId>]=1999
```

### Batch converting values to EDTF

1. Select items in the admin browse view.
2. Click Batch actions > Edit selected.
3. In the Convert to EDTF section, select the property and the data type.
4. Click Save. Invalid values are skipped and logged.

### Sorting by EDTF values

In the browse view, EDTF sort options appear automatically for any property
that has the EDTF data type assigned in a resource template.


TODO
----

- [x] Implement `ConversionTargetInterface` (Omeka S 4.2+) on `Edtf` data type to use the native batch conversion system instead of the custom `ConvertToEdtf` mechanism.
- [ ] PR/fork to fix php validator `ProfessionalWiki/EDTF`:
  - Accepts calendar-impossible dates like `1980-11-31` and `1980-02-30` (days greater than days in month);
  - Accepts qualifiers on seasons (e.g., `2020-21?`);
  - Accepts seasons/sub-year groupings in intervals (ex: `2020-21/2025-22`).
- [ ] PR/fork to fix js `edtf.js`:
  - Accepts `2023-02-29` on non-leap years;
  - Accepts reversed intervals like `1990/1980`;
  - Accepts `../..` (both sides unknown).
- [ ] Make humanization configurable via settings: ordinal "The 20th century" or literal "The 1900s", BCE/BC suffix, month format, etc.
- [ ] Apply the same settings to both JS (dialog preview) and PHP (`Edtf::render()` via `ProfessionalWiki/EDTF`).


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page.


License
-------

### Module

The Omeka source code is distributed under the GNU General Public License,
version 3 (GPLv3). The full text of this license is given in the license file.

The Omeka name is a registered trademark of the Corporation for Digital
Scholarship.

Third-party copyright in this distribution is noted where applicable.

All rights not expressly granted are reserved.

### Library

- [EDTF.js] is licensed under the terms of the BSD-2-Clause license.
- [professional-wiki/edtf] is licenced under GLPv2 or later.


Copyright
---------

- Copyright 2018-2023 [Corporation for Digital Scholarship], Vienna, Virginia, USA (NumericDataTypes)
- Copyright 2023 [University of Warwick]
- Copyright 2026 Daniel Berthereau (see [Daniel-KM] on GitLab)


[EDTF Data Type]: https://github.com/Warwick-Digital-Humanities/EdtfDataType
[Omeka S]: https://omeka.org/s
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Extended Date/Time Format]: https://www.loc.gov/standards/datetime/
[Library of Congress EDTF page]: https://www.loc.gov/standards/datetime/
[whole iso standard]: https://www.iso.org/obp/ui/#iso:std:iso:8601:-1:ed-1:v1:en
[professional-wiki/edtf]: https://github.com/ProfessionalWiki/EDTF
[CSV Import]: https://omeka.org/s/modules/CSVImport/
[Datavis]: https://omeka.org/s/modules/Datavis/
[Faceted Browse]: https://omeka.org/s/modules/FacetedBrowse/
[module issues]: https://github.com/Warwick-Digital-Humanities/EdtfDataType/issues
[EDTF.js]: https://github.com/inukshuk/edtf.js
[University of Warwick]: https://warwick.ac.uk/digitalhumanities
[Corporation for Digital Scholarship]: http://digitalscholar.org
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
