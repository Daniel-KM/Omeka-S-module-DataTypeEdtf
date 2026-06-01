'use strict';

/**
 * EDTF Data Type: real-time validation and input assistant.
 *
 * Requires edtf.js (window.edtf.parse) and jQuery.
 */
var DataTypeEdtf = (function($) {
    var escapeHtml = function(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var escapeAttr = escapeHtml;

    var translate = function(s) {
        return (typeof Omeka !== 'undefined' && Omeka.jsTranslate) ? Omeka.jsTranslate(s) : s;
    };

    /**
     * Parse a raw EDTF string into a parts object (best effort, same subset as
     * the dialog prefill).
     */
    var parseEdtfToParts = function(value) {
        if (!value || value === '..') return null;
        var qualifier = '';
        var last = value.slice(-1);
        if (last === '?' || last === '~' || last === '%') {
            qualifier = last;
            value = value.slice(0, -1);
        }
        var parts = {
            year: '', month: '', day: '', precision: '',
            uncertain: qualifier === '?' || qualifier === '%',
            approximate: qualifier === '~' || qualifier === '%',
            withTime: false, hour: '', minute: '', second: '', offset: '',
        };
        var m;
        if ((m = value.match(/^(-?\d+)(X{1,3})$/))) {
            parts.year = m[1] + '0'.repeat(m[2].length);
            parts.precision = m[2].length === 1 ? 'decade' : (m[2].length === 2 ? 'century' : 'millennium');
        } else if ((m = value.match(/^(-?\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|[+-]\d{2}:\d{2})?$/))) {
            parts.year = m[1]; parts.month = m[2]; parts.day = parseInt(m[3], 10);
            parts.withTime = true;
            parts.hour = parseInt(m[4], 10); parts.minute = parseInt(m[5], 10); parts.second = parseInt(m[6], 10);
            if (m[7]) parts.offset = m[7];
        } else if ((m = value.match(/^(-?\d{4,})-(\d{2})-(\d{2})$/))) {
            parts.year = m[1]; parts.month = m[2]; parts.day = parseInt(m[3], 10);
        } else if ((m = value.match(/^(-?\d{4,})-(\d{2})$/))) {
            parts.year = m[1]; parts.month = m[2];
        } else if ((m = value.match(/^(-?\d{1,})$/))) {
            parts.year = m[1];
        } else {
            return null;
        }
        return parts;
    };

    var humanizeRawEdtf = function(value) {
        if (!value) return '';
        var slash = value.split('/');
        if (slash.length === 2) {
            var first = parseEdtfToParts(slash[0]);
            var second = parseEdtfToParts(slash[1]);
            return humanizeEdtf({
                firstParts: first || { year: '' },
                secondParts: second || { year: '' },
                isInterval: true,
            });
        }
        var p = parseEdtfToParts(value);
        if (!p) return '';
        return humanizePart(p);
    };


    var parser = function(container) {
        var outputString = '';
        var shortExplanation = '';
        var caretLocation, caretOffset = 0;

        var rawValue = container.value;
        var isValid = true;
        try {
            edtf.parse(rawValue);
        } catch (e) {
            isValid = false;
        }
        $(container).closest('.edtf').find('.invalid-value').empty();
        var humanValue = isValid ? humanizeRawEdtf(rawValue) : '';
        var iconClass = isValid ? 'fa-check edtf-valid-icon' : 'fa-times edtf-invalid-icon';
        var btnAttrs = isValid ? '' : ' disabled="disabled"';
        var html =
            '<div class="valid-string-container" data-raw="' + escapeAttr(rawValue) + '" data-human="' + escapeAttr(humanValue) + '" data-view="raw">' +
                '<button type="button" class="edtf-toggle-view fa ' + iconClass + ' icon"' + btnAttrs + ' title="' + escapeAttr(translate('Toggle humanized view')) + '" aria-label="' + escapeAttr(translate('Toggle humanized view')) + '"></button>' +
                '<span class="edtf-display-value">' + escapeHtml(rawValue) + '</span>' +
            '</div>';
        var existing = $(container).closest('.edtf').find('.valid-string-container');
        if (existing.length > 0) {
            existing.replaceWith(html);
        } else {
            $(container).closest('.edtf').prepend(html);
        }
    };

    /**
     * Build an EDTF date string from structured parts.
     *
     * @param {object} parts  {year, month, day, precision, uncertain, approximate}
     * @returns {string}
     */
    var pad = function(n, width) {
        return String(n).padStart(width, '0');
    };

    var qualifierSuffix = function(parts) {
        if (parts.uncertain && parts.approximate) return '%';
        if (parts.uncertain) return '?';
        if (parts.approximate) return '~';
        return '';
    };

    var buildEdtfPart = function(parts) {
        if (!parts.year) {
            return '';
        }
        var year = String(parts.year);
        // Pad year to at least 4 digits (negative handled).
        if (year.charAt(0) === '-') {
            year = '-' + year.substring(1).padStart(4, '0');
        } else {
            year = year.padStart(4, '0');
        }
        var s = year;

        // Month may be a regular month (1-12) or a season/sub-year grouping
        // (21-41). Seasons force month-level precision and do not accept time
        // or qualifiers per the EDTF parser.
        var monthNum = parts.month ? parseInt(parts.month, 10) : 0;
        var isSeason = monthNum >= 21 && monthNum <= 41;
        if (isSeason) {
            return year + '-' + pad(monthNum, 2);
        }

        // Resolve empty precision from filled fields (auto mode).
        var precision = parts.precision;
        if (!precision) {
            if (monthNum && parts.day) {
                precision = 'day';
            } else if (monthNum) {
                precision = 'month';
            } else {
                precision = 'year';
            }
        }

        if (precision === 'decade') {
            s = year.substring(0, year.length - 1) + 'X';
        } else if (precision === 'century') {
            s = year.substring(0, year.length - 2) + 'XX';
        } else if (precision === 'millennium') {
            s = year.substring(0, year.length - 3) + 'XXX';
        } else if (precision === 'month' && parts.month) {
            s = year + '-' + pad(parts.month, 2);
        } else if (precision === 'day' && parts.month && parts.day) {
            s = year + '-' + pad(parts.month, 2) + '-' + pad(parts.day, 2);
            // Append time if requested.
            if (parts.withTime && parts.hour !== '' && parts.hour != null) {
                s += 'T' + pad(parts.hour, 2)
                    + ':' + pad(parts.minute || 0, 2)
                    + ':' + pad(parts.second || 0, 2);
                if (parts.offset) {
                    s += parts.offset === 'Z' ? 'Z' : parts.offset;
                }
            }
        }

        return s + qualifierSuffix(parts);
    };

    /**
     * Read an EDTF date form (fieldset) and build the EDTF part string.
     */
    var MONTH_NAMES = [
        '', 'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    var SEASON_NAMES = {
        21: 'Spring', 22: 'Summer', 23: 'Autumn', 24: 'Winter',
        25: 'Spring - Northern', 26: 'Summer - Northern', 27: 'Autumn - Northern', 28: 'Winter - Northern',
        29: 'Spring - Southern', 30: 'Summer - Southern', 31: 'Autumn - Southern', 32: 'Winter - Southern',
        33: 'Q1', 34: 'Q2', 35: 'Q3', 36: 'Q4',
        37: 'Quadrimester 1', 38: 'Quadrimester 2', 39: 'Quadrimester 3',
        40: 'Semester 1', 41: 'Semester 2',
    };

    var ordinal = function(n) {
        var abs = Math.abs(n);
        var v = abs % 100;
        var suffix;
        if (v >= 11 && v <= 13) suffix = 'th';
        else if (abs % 10 === 1) suffix = 'st';
        else if (abs % 10 === 2) suffix = 'nd';
        else if (abs % 10 === 3) suffix = 'rd';
        else suffix = 'th';
        return n + suffix;
    };

    /**
     * Humanize a structured EDTF part.
     */
    var humanizePart = function(parts) {
        if (!parts.year) return '';
        var yearInt = parseInt(parts.year, 10);
        var isBc = yearInt < 0;
        var absYear = Math.abs(yearInt);
        var year = String(parts.year);
        var monthNum = parts.month ? parseInt(parts.month, 10) : 0;
        var reduced = parts.precision;
        var s;

        if (reduced === 'millennium') {
            // 1XXX covers 1000-1999: 2nd millennium.
            var millNum = Math.floor(absYear / 1000) + 1;
            s = translate('%s millennium').replace('%s', ordinal(millNum));
            if (isBc) s += ' ' + translate('BCE');
        } else if (reduced === 'century') {
            // 19XX covers 1900-1999: 20th century.
            var centuryNum = Math.floor(absYear / 100) + 1;
            s = translate('%s century').replace('%s', ordinal(centuryNum));
            if (isBc) s += ' ' + translate('BCE');
        } else if (reduced === 'decade') {
            // 198X covers 1980-1989: the 1980s.
            var decadeBase = Math.floor(absYear / 10) * 10;
            s = translate('%ss').replace('%s', (isBc ? '-' : '') + decadeBase);
            if (isBc) s += ' ' + translate('BCE');
        } else if (monthNum >= 21 && monthNum <= 41) {
            s = translate(SEASON_NAMES[monthNum] || '') + ' ' + year;
        } else if (monthNum >= 1 && monthNum <= 12 && parts.day) {
            s = translate(MONTH_NAMES[monthNum]) + ' ' + parseInt(parts.day, 10) + ', ' + year;
        } else if (monthNum >= 1 && monthNum <= 12) {
            s = translate(MONTH_NAMES[monthNum]) + ' ' + year;
        } else {
            s = year;
        }

        if (parts.withTime && parts.hour !== '' && parts.hour != null) {
            var h = pad(parts.hour, 2);
            var mn = pad(parts.minute || 0, 2);
            var sc = pad(parts.second || 0, 2);
            s += ' ' + translate('at') + ' ' + h + ':' + mn + ':' + sc;
            if (parts.offset === 'Z') s += ' UTC';
            else if (parts.offset) s += ' ' + parts.offset;
        }

        if (parts.uncertain && parts.approximate) {
            s += ' (' + translate('uncertain and approximate') + ')';
        } else if (parts.uncertain) {
            s += ' (' + translate('uncertain') + ')';
        } else if (parts.approximate) {
            s += ' (' + translate('approximate') + ')';
        }
        return s;
    };

    var humanizeEdtf = function(ctx) {
        var first = humanizePart(ctx.firstParts);
        if (!ctx.isInterval) return first;
        var second = humanizePart(ctx.secondParts);
        if (!first && !second) return '';
        if (!first && ctx.unknownSide) return translate('Before %1$s').replace('%1$s', second);
        if (!first) return translate('Until %1$s').replace('%1$s', second);
        if (!second && ctx.unknownSide) return translate('After %1$s').replace('%1$s', first);
        if (!second) return translate('Since %1$s').replace('%1$s', first);
        return translate('%1$s to %2$s').replace('%1$s', first).replace('%2$s', second);
    };

    var snapshotFieldset = function($fs) {
        return {
            year: $fs.find('.edtf-assistant-year').val(),
            month: $fs.find('.edtf-assistant-month').val(),
            day: $fs.find('.edtf-assistant-day').val(),
            precision: $fs.find('.edtf-assistant-precision').val(),
            uncertain: $fs.find('.edtf-assistant-uncertain').prop('checked'),
            approximate: $fs.find('.edtf-assistant-approximate').prop('checked'),
            withTime: $fs.find('.edtf-assistant-with-time').prop('checked'),
            hour: $fs.find('.edtf-assistant-hour').val(),
            minute: $fs.find('.edtf-assistant-minute').val(),
            second: $fs.find('.edtf-assistant-second').val(),
            offset: $fs.find('.edtf-assistant-offset').val(),
        };
    };

    var restoreFieldset = function($fs, snap) {
        $fs.find('.edtf-assistant-year').val(snap.year);
        $fs.find('.edtf-assistant-month').val(snap.month);
        $fs.find('.edtf-assistant-day').val(snap.day);
        $fs.find('.edtf-assistant-precision').val(snap.precision);
        $fs.find('.edtf-assistant-uncertain').prop('checked', !!snap.uncertain);
        $fs.find('.edtf-assistant-approximate').prop('checked', !!snap.approximate);
        $fs.find('.edtf-assistant-with-time').prop('checked', !!snap.withTime);
        $fs.find('.edtf-assistant-row-time').toggle(!!snap.withTime);
        $fs.find('.edtf-assistant-hour').val(snap.hour);
        $fs.find('.edtf-assistant-minute').val(snap.minute);
        $fs.find('.edtf-assistant-second').val(snap.second);
        $fs.find('.edtf-assistant-offset').val(snap.offset);
    };

    var copyFieldset = function($from, $to) {
        restoreFieldset($to, snapshotFieldset($from));
    };

    /**
     * Read raw form fields as an object (no EDTF formatting).
     */
    var readPartsRaw = function($fieldset) {
        return {
            year: $fieldset.find('.edtf-assistant-year').val(),
            month: $fieldset.find('.edtf-assistant-month').val(),
            day: $fieldset.find('.edtf-assistant-day').val(),
            precision: $fieldset.find('.edtf-assistant-precision').val(),
            uncertain: $fieldset.find('.edtf-assistant-uncertain').prop('checked'),
            approximate: $fieldset.find('.edtf-assistant-approximate').prop('checked'),
            withTime: $fieldset.find('.edtf-assistant-with-time').prop('checked'),
            hour: $fieldset.find('.edtf-assistant-hour').val(),
            minute: $fieldset.find('.edtf-assistant-minute').val(),
            second: $fieldset.find('.edtf-assistant-second').val(),
            offset: $fieldset.find('.edtf-assistant-offset').val(),
        };
    };

    var isLeapYear = function(y) {
        y = parseInt(y, 10);
        return (y % 4 === 0 && y % 100 !== 0) || (y % 400 === 0);
    };

    var daysInMonth = function(y, m) {
        y = parseInt(y, 10);
        m = parseInt(m, 10);
        if (m === 2) return isLeapYear(y) ? 29 : 28;
        if ([4, 6, 9, 11].indexOf(m) !== -1) return 30;
        return 31;
    };

    /**
     * Validate a structured EDTF input per the ISO 8601-2:2019 norm.
     *
     * Returns an empty string if valid, or a human-readable error.
     */
    var validateEdtf = function(result, ctx) {
        var first = ctx.firstParts;
        var second = ctx.secondParts;

        if (ctx.isInterval) {
            var firstEmpty = !first.year;
            var secondEmpty = !second.year;
            if (firstEmpty && secondEmpty) {
                return { message: translate('At least one side of the interval must be specified'), index: 0, field: 'year' };
            }
            if (isSeason(first)) {
                return { message: translate('Seasons and sub-year groupings cannot be used in intervals'), index: 0, field: 'month' };
            }
            if (isSeason(second)) {
                return { message: translate('Seasons and sub-year groupings cannot be used in intervals'), index: 1, field: 'month' };
            }
            var e1 = validateSinglePart(first);
            if (e1) { e1.index = 0; return e1; }
            var e2 = validateSinglePart(second);
            if (e2) { e2.index = 1; return e2; }
            if (!firstEmpty && !secondEmpty) {
                var start = comparableDate(first);
                var end = comparableDate(second);
                if (start !== null && end !== null && start > end) {
                    return { message: translate('Interval end must be on or after its start'), index: 1, field: 'year' };
                }
            }
            return null;
        }

        var e = validateSinglePart(first);
        if (e) { e.index = 0; return e; }
        return null;
    };

    var isSeason = function(parts) {
        var m = parts && parts.month ? parseInt(parts.month, 10) : 0;
        return m >= 21 && m <= 41;
    };

    /**
     * Validate a single date (non-interval) per EDTF norm.
     */
    var validateSinglePart = function(parts) {
        if (!parts.year) return null;

        if (isSeason(parts) && (parts.uncertain || parts.approximate)) {
            return { message: translate('Qualifiers (uncertain, approximate) cannot be used with seasons'), field: 'uncertain' };
        }

        // Qualifiers on reduced precision (decade/century/millennium)
        // combine a Level 2 feature with a Level 1 qualifier and are not
        // explicitly allowed by the ISO 8601-2:2019 grammar.
        var reduced = ['decade', 'century', 'millennium'].indexOf(parts.precision) !== -1;
        if (reduced && (parts.uncertain || parts.approximate)) {
            return { message: translate('Qualifiers (uncertain, approximate) cannot be used with reduced precision'), field: 'uncertain' };
        }

        if (parts.withTime && (isSeason(parts) || ['decade', 'century', 'millennium'].indexOf(parts.precision) !== -1)) {
            return { message: translate('Time is only allowed with day precision'), field: 'with-time' };
        }

        if (!isSeason(parts) && parts.month) {
            var m = parseInt(parts.month, 10);
            if (m < 1 || m > 12) {
                return { message: translate('Invalid month'), field: 'month' };
            }
            if (parts.day) {
                var d = parseInt(parts.day, 10);
                if (d < 1 || d > daysInMonth(parts.year, m)) {
                    return { message: translate('Invalid day for this month'), field: 'day' };
                }
            }
        }

        if (parts.withTime && parts.hour !== '' && parts.hour != null) {
            var h = parseInt(parts.hour, 10);
            var mn = parts.minute !== '' && parts.minute != null ? parseInt(parts.minute, 10) : 0;
            var sc = parts.second !== '' && parts.second != null ? parseInt(parts.second, 10) : 0;
            if (h < 0 || h > 23) return { message: translate('Invalid hour'), field: 'hour' };
            if (mn < 0 || mn > 59) return { message: translate('Invalid minute'), field: 'minute' };
            if (sc < 0 || sc > 59) return { message: translate('Invalid second'), field: 'second' };
        }

        return null;
    };

    /**
     * Convert parts to a comparable numeric date (YYYYMMDD.HHMMSS).
     *
     * Returns null if parts cannot be compared (seasons, reduced precision).
     */
    var comparableDate = function(parts) {
        if (!parts.year) return null;
        if (isSeason(parts)) return null;
        if (['decade', 'century', 'millennium'].indexOf(parts.precision) !== -1) return null;
        var y = parseInt(parts.year, 10);
        var m = parts.month ? parseInt(parts.month, 10) : 1;
        var d = parts.day ? parseInt(parts.day, 10) : 1;
        return y * 10000 + m * 100 + d;
    };

    var readFormPart = function($fieldset) {
        return buildEdtfPart({
            year: $fieldset.find('.edtf-assistant-year').val(),
            month: $fieldset.find('.edtf-assistant-month').val(),
            day: $fieldset.find('.edtf-assistant-day').val(),
            precision: $fieldset.find('.edtf-assistant-precision').val(),
            uncertain: $fieldset.find('.edtf-assistant-uncertain').prop('checked'),
            approximate: $fieldset.find('.edtf-assistant-approximate').prop('checked'),
            withTime: $fieldset.find('.edtf-assistant-with-time').prop('checked'),
            hour: $fieldset.find('.edtf-assistant-hour').val(),
            minute: $fieldset.find('.edtf-assistant-minute').val(),
            second: $fieldset.find('.edtf-assistant-second').val(),
            offset: $fieldset.find('.edtf-assistant-offset').val(),
        });
    };

    /**
     * UTC offset options (from -12:00 to +14:00).
     * @see https://en.wikipedia.org/wiki/List_of_UTC_time_offsets
     */
    var offsetOptions = function() {
        var offsets = [
            '-12:00', '-11:00', '-10:00', '-09:30', '-09:00', '-08:00',
            '-07:00', '-06:00', '-05:00', '-04:00', '-03:30', '-03:00',
            '-02:00', '-01:00', '+00:00', '+01:00', '+02:00', '+03:00',
            '+03:30', '+04:00', '+04:30', '+05:00', '+05:30', '+05:45',
            '+06:00', '+06:30', '+07:00', '+08:00', '+08:45', '+09:00',
            '+09:30', '+10:00', '+10:30', '+11:00', '+12:00', '+12:45',
            '+13:00', '+14:00',
        ];
        return offsets.map(function(o) {
            return '<option value="' + o + '">' + o + '</option>';
        }).join('');
    };

    /**
     * HTML template for a single EDTF date fieldset.
     */
    var datePartHtml = function(translate) {
        return ''
            + '<fieldset class="edtf-assistant-part">'
            +   '<div class="edtf-assistant-row edtf-assistant-row-date">'
            +     '<input type="number" inputmode="numeric" class="edtf-assistant-year" step="1"'
            +       ' placeholder="' + translate('Year') + '" aria-label="' + translate('Year') + '">'
            +     '<select class="edtf-assistant-month" aria-label="' + translate('Month') + '">'
            +       '<option value="">' + translate('Month') + '</option>'
            +       '<optgroup label="' + translate('Months') + '">'
            +         '<option value="01">01 — ' + translate('January') + '</option>'
            +         '<option value="02">02 — ' + translate('February') + '</option>'
            +         '<option value="03">03 — ' + translate('March') + '</option>'
            +         '<option value="04">04 — ' + translate('April') + '</option>'
            +         '<option value="05">05 — ' + translate('May') + '</option>'
            +         '<option value="06">06 — ' + translate('June') + '</option>'
            +         '<option value="07">07 — ' + translate('July') + '</option>'
            +         '<option value="08">08 — ' + translate('August') + '</option>'
            +         '<option value="09">09 — ' + translate('September') + '</option>'
            +         '<option value="10">10 — ' + translate('October') + '</option>'
            +         '<option value="11">11 — ' + translate('November') + '</option>'
            +         '<option value="12">12 — ' + translate('December') + '</option>'
            +       '</optgroup>'
            +       '<optgroup label="' + translate('Seasons') + '">'
            +         '<option value="21">21 — ' + translate('Spring') + '</option>'
            +         '<option value="22">22 — ' + translate('Summer') + '</option>'
            +         '<option value="23">23 — ' + translate('Autumn') + '</option>'
            +         '<option value="24">24 — ' + translate('Winter') + '</option>'
            +       '</optgroup>'
            +       '<optgroup label="' + translate('Seasons (Northern Hemisphere)') + '">'
            +         '<option value="25">25 — ' + translate('Spring - Northern') + '</option>'
            +         '<option value="26">26 — ' + translate('Summer - Northern') + '</option>'
            +         '<option value="27">27 — ' + translate('Autumn - Northern') + '</option>'
            +         '<option value="28">28 — ' + translate('Winter - Northern') + '</option>'
            +       '</optgroup>'
            +       '<optgroup label="' + translate('Seasons (Southern Hemisphere)') + '">'
            +         '<option value="29">29 — ' + translate('Spring - Southern') + '</option>'
            +         '<option value="30">30 — ' + translate('Summer - Southern') + '</option>'
            +         '<option value="31">31 — ' + translate('Autumn - Southern') + '</option>'
            +         '<option value="32">32 — ' + translate('Winter - Southern') + '</option>'
            +       '</optgroup>'
            +       '<optgroup label="' + translate('Quarters') + '">'
            +         '<option value="33">33 — ' + translate('Q1') + '</option>'
            +         '<option value="34">34 — ' + translate('Q2') + '</option>'
            +         '<option value="35">35 — ' + translate('Q3') + '</option>'
            +         '<option value="36">36 — ' + translate('Q4') + '</option>'
            +       '</optgroup>'
            +       '<optgroup label="' + translate('Quadrimesters') + '">'
            +         '<option value="37">37 — ' + translate('Quadrimester 1') + '</option>'
            +         '<option value="38">38 — ' + translate('Quadrimester 2') + '</option>'
            +         '<option value="39">39 — ' + translate('Quadrimester 3') + '</option>'
            +       '</optgroup>'
            +       '<optgroup label="' + translate('Semesters') + '">'
            +         '<option value="40">40 — ' + translate('Semester 1') + '</option>'
            +         '<option value="41">41 — ' + translate('Semester 2') + '</option>'
            +       '</optgroup>'
            +     '</select>'
            +     '<input type="number" inputmode="numeric" class="edtf-assistant-day" min="1" max="31" step="1"'
            +       ' placeholder="' + translate('Day') + '" aria-label="' + translate('Day') + '">'
            +     '<label class="edtf-assistant-with-time-toggle" title="' + translate('Add time') + '">'
            +       '<span class="edtf-assistant-clock-icon" aria-hidden="true"></span>'
            +       '<input type="checkbox" class="edtf-assistant-with-time" aria-label="' + translate('Add time') + '">'
            +     '</label>'
            +   '</div>'
            +   '<div class="edtf-assistant-row edtf-assistant-row-time" style="display:none;">'
            +     '<input type="number" inputmode="numeric" class="edtf-assistant-hour" min="0" max="23" step="1"'
            +       ' placeholder="' + translate('Hour') + '" aria-label="' + translate('Hour') + '">'
            +     '<input type="number" inputmode="numeric" class="edtf-assistant-minute" min="0" max="59" step="1"'
            +       ' placeholder="' + translate('Minute') + '" aria-label="' + translate('Minute') + '">'
            +     '<input type="number" inputmode="numeric" class="edtf-assistant-second" min="0" max="59" step="1"'
            +       ' placeholder="' + translate('Second') + '" aria-label="' + translate('Second') + '">'
            +     '<select class="edtf-assistant-offset" aria-label="' + translate('Offset') + '">'
            +       '<option value="">' + translate('Offset') + '</option>'
            +       '<option value="Z">Z</option>'
            +       offsetOptions()
            +     '</select>'
            +   '</div>'
            +   '<div class="edtf-assistant-row edtf-assistant-row-qualifiers">'
            +     '<select class="edtf-assistant-precision" aria-label="' + translate('Precision') + '">'
            +       '<option value="" selected>' + translate('Precision') + '</option>'
            +       '<option value="day">' + translate('Day') + '</option>'
            +       '<option value="month">' + translate('Month') + '</option>'
            +       '<option value="year">' + translate('Year') + '</option>'
            +       '<option value="decade">' + translate('Decade (198X)') + '</option>'
            +       '<option value="century">' + translate('Century (19XX)') + '</option>'
            +       '<option value="millennium">' + translate('Millennium (1XXX)') + '</option>'
            +     '</select>'
            +     '<label class="edtf-assistant-approximate-label"><input type="checkbox" class="edtf-assistant-approximate"> ' + translate('Approximate (~)') + '</label>'
            +     '<label><input type="checkbox" class="edtf-assistant-uncertain"> ' + translate('Uncertain (?)') + '</label>'
            +   '</div>'
            + '</fieldset>';
    };

    /**
     * Build the full assistant dialog HTML, following the Common module
     * dialog-common structure (dialog-background > dialog-panel > ...).
     */
    var assistantHtml = function(translate) {
        return ''
            + '<dialog class="dialog-common edtf-assistant-popup" aria-labelledby="edtf-assistant-heading">'
            +   '<div class="dialog-background">'
            +     '<div class="dialog-panel">'
            +       '<div class="dialog-header">'
            +         '<button type="button" class="dialog-header-close-button edtf-assistant-close">'
            +           '<span class="dialog-close" aria-hidden="true">🗙</span>'
            +           '<span class="dialog-close-label">' + translate('Close') + '</span>'
            +         '</button>'
            +       '</div>'
            +       '<div class="dialog-contents">'
            +         '<div class="dialog-heading"><h2 id="edtf-assistant-heading">' + translate('Assistant for Extended Date/Time Format') + '</h2></div>'
            +         '<div class="dialog-body">'
            +           '<div class="edtf-assistant-top-row">'
            +             '<label class="edtf-assistant-interval-toggle">'
            +               '<input type="checkbox" class="edtf-assistant-interval"> '
            +               translate('Interval (two dates)')
            +             '</label>'
            +             '<span class="edtf-assistant-pre-reform-warning" title="' + translate('Date before the Gregorian reform (4th/15th October 1582). Enter the value in proleptic Gregorian.') + '" hidden>'
            +               '<span class="fas fa-history" aria-hidden="true"></span>'
            +             '</span>'
            +             '<button type="button" class="edtf-assistant-help-toggle" aria-expanded="false" aria-controls="edtf-assistant-help" title="' + translate('About calendar and year numbering') + '">'
            +               '<span class="fas fa-question-circle" aria-hidden="true"></span>'
            +               '<span class="screen-reader-text">' + translate('Help') + '</span>'
            +             '</button>'
            +           '</div>'
            +           '<div id="edtf-assistant-help" class="edtf-assistant-calendar-warning messages" hidden>'
            +             '<div class="warning">'
            +               translate('The extended format uses the proleptic Gregorian calendar with astronomical year numbering (year 0 = 1 BCE). Historical dates before the Gregorian reform of 1582 (or later in some countries) are usually recorded in the Julian calendar in sources and must be converted before entry. For instance:')
            +              '<ul>'
            +                '<li>' + translate('the Battle of Lepanto (7 October 1571 Julian) must be entered as 1571-10-17;') + '</li>'
            +                '<li>' + translate('the Battle of Marathon (12 September 490 BCE Julian) as -0489-09-07.') + '</li>'
            +              '</ul>'
            +             '</div>'
            +           '</div>'
            +           '<div class="edtf-assistant-parts">'
            +             datePartHtml(translate)
            +             '<div class="edtf-assistant-interval-tools" style="display:none;">'
            +               '<button type="button" class="edtf-assistant-copy-to-end edtf-assistant-link" title="' + translate('Copy start to end') + '"><span class="fas fa-arrow-down" aria-hidden="true"></span> ' + translate('Copy') + '</button>'
            +               '<button type="button" class="edtf-assistant-swap edtf-assistant-link" title="' + translate('Swap start and end') + '"><span class="fas fa-exchange-alt fa-rotate-90" aria-hidden="true"></span> ' + translate('Swap') + '</button>'
            +               '<label class="edtf-assistant-empty-unknown-label" style="display:none;" title="' + translate('Checked: the empty side means unknown. Unchecked: it means open, extending indefinitely (..)') + '">'
            +                 '<input type="checkbox" class="edtf-assistant-empty-unknown"> '
            +                 translate('Unknown side')
            +               '</label>'
            +             '</div>'
            +             '<fieldset class="edtf-assistant-part edtf-assistant-end" style="display:none;">'
            +               datePartHtml(translate).replace('<fieldset class="edtf-assistant-part">', '').replace(/<\/fieldset>$/, '')
            +             '</fieldset>'
            +           '</div>'
            +           '<div class="edtf-assistant-preview">'
            +             '<div class="edtf-assistant-preview-main">'
            +               '<code class="edtf-assistant-result"></code>'
            +               '<span class="edtf-assistant-error"></span>'
            +             '</div>'
            +             '<div class="edtf-assistant-humanized"></div>'
            +           '</div>'
            +         '</div>'
            +       '</div>'
            +       '<div class="dialog-footer">'
            +         '<button type="button" class="edtf-assistant-cancel button">' + translate('Cancel') + '</button>'
            +         '<button type="button" class="edtf-assistant-apply button">' + translate('Apply') + '</button>'
            +       '</div>'
            +     '</div>'
            +   '</div>'
            + '</dialog>';
    };

    /**
     * Open the assistant popup for the given input.
     */
    var translate = function(s) {
        return (typeof Omeka !== 'undefined' && Omeka.jsTranslate) ? Omeka.jsTranslate(s) : s;
    };

    var openAssistant = function(input, triggerBtn) {
        var $input = $(input);

        // Only one dialog at a time across the page.
        $('.edtf-assistant-popup').each(function() {
            if (typeof this.close === 'function') this.close();
            $(this).remove();
        });

        var $popup = $(assistantHtml(translate));
        $('body').append($popup);
        var dialog = $popup[0];
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            $popup.attr('open', 'open');
        }

        // Focus the first year field on open.
        setTimeout(function() {
            $popup.find('.edtf-assistant-year').eq(0).trigger('focus').select();
        }, 0);

        // Toggle global pre-reform warning: visible when any Gregorian part is
        // strictly before 1582-10-15. Computed only on blur/change to avoid
        // flashing while the user is still typing the year (e.g. "1" before
        // "1980").
        var updatePreReformWarning = function() {
            var $parts = $popup.find('.edtf-assistant-parts .edtf-assistant-part:visible');
            var anyPre = false;
            $parts.each(function() {
                var $fs = $(this);
                var cal = $fs.find('.edtf-assistant-calendar').val();
                if (cal) {
                    return;
                }
                var y = parseInt($fs.find('.edtf-assistant-year').val(), 10);
                if (isNaN(y)) {
                    return;
                }
                var m = parseInt($fs.find('.edtf-assistant-month').val(), 10);
                var d = parseInt($fs.find('.edtf-assistant-day').val(), 10);
                var pre = false;
                if (isNaN(m)) {
                    pre = y < 1582;
                } else if (isNaN(d)) {
                    pre = (y < 1582) || (y === 1582 && m < 10);
                } else {
                    pre = (y < 1582)
                        || (y === 1582 && m < 10)
                        || (y === 1582 && m === 10 && d < 15);
                }
                if (pre) anyPre = true;
            });
            $popup.find('.edtf-assistant-top-row .edtf-assistant-pre-reform-warning').prop('hidden', !anyPre);
        };

        var updatePreview = function() {
            var $parts = $popup.find('.edtf-assistant-parts .edtf-assistant-part');
            var isInterval = $popup.find('.edtf-assistant-interval').prop('checked');
            var first = readFormPart($parts.eq(0));
            var result = first;
            if (isInterval) {
                var second = readFormPart($parts.eq(1));
                // Toggle the "Unknown side" checkbox visibility: shown only
                // when exactly one side is empty. When checked, the empty side
                // is left blank (unknown); otherwise it becomes "..".
                var $unkLabel = $popup.find('.edtf-assistant-empty-unknown-label');
                var $unkCb = $popup.find('.edtf-assistant-empty-unknown');
                var oneEmpty = (!!first) !== (!!second);
                $unkLabel.toggle(oneEmpty);
                if (!oneEmpty) {
                    $unkCb.prop('checked', false);
                }
                var unknownSide = $unkCb.prop('checked');
                var filler = unknownSide ? '' : '..';
                // EDTF requires at least one side of an interval to be a normal
                // date; "../.." is not a valid interval.
                if (first || second) {
                    result = (first || filler) + '/' + (second || filler);
                } else {
                    result = '';
                }
            } else {
                $popup.find('.edtf-assistant-empty-unknown-label').hide();
            }
            var $result = $popup.find('.edtf-assistant-result');
            var $apply = $popup.find('.edtf-assistant-apply');
            $result.text(result || '—');
            // Clear previous field-level error state.
            $popup.find('.edtf-assistant-field-error').removeClass('edtf-assistant-field-error');
            var errorMsg = '';
            var isValid = false;
            var firstRaw = readPartsRaw($parts.eq(0));
            var secondRaw = isInterval ? readPartsRaw($parts.eq(1)) : null;
            if (result && result !== '—' && result !== '..') {
                var err = validateEdtf(result, {
                    firstParts: firstRaw,
                    secondParts: secondRaw,
                    isInterval: isInterval,
                });
                if (err) {
                    errorMsg = err.message;
                    var $target = $parts.eq(err.index || 0).find('.edtf-assistant-' + err.field);
                    $target.addClass('edtf-assistant-field-error');
                }
                isValid = !err;
            }
            $result.toggleClass('edtf-assistant-invalid', !!result && !isValid);
            $apply.prop('disabled', !!result && !isValid);
            $popup.find('.edtf-assistant-error').text(errorMsg);
            // Humanized display: shown only when the value is valid.
            var humanized = isValid ? humanizeEdtf({
                firstParts: firstRaw,
                secondParts: secondRaw,
                isInterval: isInterval,
                unknownSide: $popup.find('.edtf-assistant-empty-unknown').prop('checked'),
            }) : '';
            $popup.find('.edtf-assistant-humanized').text(humanized);
        };

        // Toggle interval end fieldset + tools.
        $popup.on('change', '.edtf-assistant-interval', function() {
            $popup.find('.edtf-assistant-end, .edtf-assistant-interval-tools').toggle(this.checked);
            updatePreview();
            updatePreReformWarning();
        });

        // Copy start fieldset to end fieldset.
        $popup.on('click', '.edtf-assistant-copy-to-end', function(e) {
            e.preventDefault();
            var $start = $popup.find('.edtf-assistant-part').eq(0);
            var $end = $popup.find('.edtf-assistant-part').eq(1);
            copyFieldset($start, $end);
            updateSeasonState($end);
            updateDayMax($end);
            updatePreview();
        });

        // Swap start and end fieldsets.
        $popup.on('click', '.edtf-assistant-swap', function(e) {
            e.preventDefault();
            var $start = $popup.find('.edtf-assistant-part').eq(0);
            var $end = $popup.find('.edtf-assistant-part').eq(1);
            var tmp = snapshotFieldset($start);
            copyFieldset($end, $start);
            restoreFieldset($end, tmp);
            updateSeasonState($start);
            updateSeasonState($end);
            updateDayMax($start);
            updateDayMax($end);
            updatePreview();
        });

        // Toggle time row within a fieldset.
        $popup.on('change', '.edtf-assistant-with-time', function() {
            $(this).closest('.edtf-assistant-part').find('.edtf-assistant-row-time').toggle(this.checked);
            updatePreview();
        });

        // When a season/sub-year (21-41) is selected, disable fields that are
        // not valid in that mode: day, time, precision and qualifiers (?, ~).
        var updateSeasonState = function($fieldset) {
            var monthVal = parseInt($fieldset.find('.edtf-assistant-month').val(), 10);
            var isSeason = monthVal >= 21 && monthVal <= 41;
            $fieldset.find('.edtf-assistant-day').prop('disabled', isSeason);
            var $withTime = $fieldset.find('.edtf-assistant-with-time');
            $withTime.prop('disabled', isSeason);
            if (isSeason && $withTime.prop('checked')) {
                $withTime.prop('checked', false);
                $fieldset.find('.edtf-assistant-row-time').hide();
            }
            $fieldset.find('.edtf-assistant-row-time input, .edtf-assistant-row-time select')
                .prop('disabled', isSeason);
            $fieldset.find('.edtf-assistant-with-time-toggle').toggleClass('edtf-assistant-disabled', isSeason);
            $fieldset.find('.edtf-assistant-precision').prop('disabled', isSeason);
            var $unc = $fieldset.find('.edtf-assistant-uncertain');
            var $app = $fieldset.find('.edtf-assistant-approximate');
            $unc.prop('disabled', isSeason);
            $app.prop('disabled', isSeason);
            if (isSeason) {
                $unc.prop('checked', false);
                $app.prop('checked', false);
            }
        };
        $popup.on('change', '.edtf-assistant-month', function() {
            updateSeasonState($(this).closest('.edtf-assistant-part'));
            updateDayMax($(this).closest('.edtf-assistant-part'));
        });
        $popup.on('input', '.edtf-assistant-year', function() {
            updateDayMax($(this).closest('.edtf-assistant-part'));
        });

        var updateDayMax = function($fieldset) {
            var year = $fieldset.find('.edtf-assistant-year').val();
            var month = parseInt($fieldset.find('.edtf-assistant-month').val(), 10);
            var $day = $fieldset.find('.edtf-assistant-day');
            if (year && month >= 1 && month <= 12) {
                var max = daysInMonth(year, month);
                $day.attr('max', max);
                if (parseInt($day.val(), 10) > max) {
                    $day.val(max);
                }
            } else {
                $day.attr('max', 31);
            }
        };

        // Update preview on any change (debounced on keystroke).
        var previewTimer = null;
        var debouncedPreview = function() {
            if (previewTimer) clearTimeout(previewTimer);
            previewTimer = setTimeout(updatePreview, 80);
        };
        $popup.on('input', 'input', debouncedPreview);
        $popup.on('change', 'input, select', updatePreview);
        // Pre-reform warning: only on blur or value commit, not on each
        // keystroke (avoid flashing while typing the year).
        $popup.on('blur', '.edtf-assistant-year, .edtf-assistant-month, .edtf-assistant-day, .edtf-assistant-calendar', updatePreReformWarning);
        $popup.on('change', '.edtf-assistant-month, .edtf-assistant-day, .edtf-assistant-calendar', updatePreReformWarning);

        var closeDialog = function() {
            if (typeof dialog.close === 'function') dialog.close();
            $popup.remove();
            // Restore focus on the trigger button.
            if (triggerBtn) {
                try { triggerBtn.focus(); } catch (e) {}
            }
        };

        // Enter key applies when valid (except in textarea/select).
        $popup.on('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'SELECT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                if (!$popup.find('.edtf-assistant-apply').prop('disabled')) {
                    $popup.find('.edtf-assistant-apply').trigger('click');
                }
            }
        });

        // Apply: set input value and trigger validation.
        $popup.on('click', '.edtf-assistant-apply', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ($(this).prop('disabled')) {
                return;
            }
            var value = $popup.find('.edtf-assistant-result').text();
            if (value && value !== '—') {
                $input.val(value);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            closeDialog();
        });

        // Cancel / close.
        $popup.on('click', '.edtf-assistant-help-toggle', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $help = $popup.find('#edtf-assistant-help');
            var expanded = $btn.attr('aria-expanded') === 'true';
            $btn.attr('aria-expanded', expanded ? 'false' : 'true');
            $help.prop('hidden', expanded);
        });

        $popup.on('click', '.edtf-assistant-cancel, .edtf-assistant-close', closeDialog);

        // Close on backdrop click.
        $popup.on('click', function(e) {
            if (e.target === dialog) closeDialog();
        });

        // Prefill form from current input value.
        var current = ($input.val() || '').trim();
        if (current) {
            var parts = current.split('/');
            prefillPart($popup.find('.edtf-assistant-part').eq(0), parts[0]);
            if (parts.length === 2) {
                $popup.find('.edtf-assistant-interval').prop('checked', true).trigger('change');
                prefillPart($popup.find('.edtf-assistant-part').eq(1), parts[1]);
            }
            $popup.find('.edtf-assistant-part').each(function() {
                updateSeasonState($(this));
            });
            updatePreview();
            updatePreReformWarning();
        }
    };

    /**
     * Fill a fieldset from a single EDTF date string.
     */
    var prefillPart = function($fieldset, value) {
        if (!value || value === '..') return;

        // Qualifier suffix (?, ~, %).
        var qualifier = '';
        var last = value.slice(-1);
        if (last === '?' || last === '~' || last === '%') {
            qualifier = last;
            value = value.slice(0, -1);
        }
        $fieldset.find('.edtf-assistant-uncertain').prop('checked', qualifier === '?' || qualifier === '%');
        $fieldset.find('.edtf-assistant-approximate').prop('checked', qualifier === '~' || qualifier === '%');

        // Reduced precision: decade / century / millennium (trailing X's).
        var mReduced = value.match(/^(-?\d+)(X{1,3})$/);
        if (mReduced) {
            var base = mReduced[1];
            var xs = mReduced[2].length;
            $fieldset.find('.edtf-assistant-year').val(base + '0'.repeat(xs));
            $fieldset.find('.edtf-assistant-precision').val(
                xs === 1 ? 'decade' : (xs === 2 ? 'century' : 'millennium')
            );
            return;
        }

        // Datetime: YYYY-MM-DDTHH:MM:SS[Z|±HH:MM]
        var mDt = value.match(/^(-?\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|[+-]\d{2}:\d{2})?$/);
        if (mDt) {
            $fieldset.find('.edtf-assistant-year').val(mDt[1]);
            $fieldset.find('.edtf-assistant-month').val(mDt[2]);
            $fieldset.find('.edtf-assistant-day').val(parseInt(mDt[3], 10));
            $fieldset.find('.edtf-assistant-with-time').prop('checked', true).trigger('change');
            $fieldset.find('.edtf-assistant-hour').val(parseInt(mDt[4], 10));
            $fieldset.find('.edtf-assistant-minute').val(parseInt(mDt[5], 10));
            $fieldset.find('.edtf-assistant-second').val(parseInt(mDt[6], 10));
            if (mDt[7]) $fieldset.find('.edtf-assistant-offset').val(mDt[7]);
            return;
        }

        // YYYY-MM-DD
        var mYmd = value.match(/^(-?\d{4,})-(\d{2})-(\d{2})$/);
        if (mYmd) {
            $fieldset.find('.edtf-assistant-year').val(mYmd[1]);
            $fieldset.find('.edtf-assistant-month').val(mYmd[2]);
            $fieldset.find('.edtf-assistant-day').val(parseInt(mYmd[3], 10));
            return;
        }

        // YYYY-NN (month 01-12 or season/sub-year 21-41).
        var mYm = value.match(/^(-?\d{4,})-(\d{2})$/);
        if (mYm) {
            $fieldset.find('.edtf-assistant-year').val(mYm[1]);
            $fieldset.find('.edtf-assistant-month').val(mYm[2]);
            return;
        }

        // YYYY (plain year).
        var mY = value.match(/^(-?\d{1,})$/);
        if (mY) {
            $fieldset.find('.edtf-assistant-year').val(mY[1]);
        }
    };

    /**
     * Auto-uppercase EDTF lettered tokens (X, T, Y, Z, E, S) so the user can
     * type them in lowercase without worrying about case.
     */
    var autoUppercase = function(input) {
        var val = input.value;
        var upper = val.replace(/[xtyzes]/g, function(c) { return c.toUpperCase(); });
        if (upper !== val) {
            var pos = input.selectionStart;
            input.value = upper;
            try { input.setSelectionRange(pos, pos); } catch (e) {}
        }
    };

    var addParserEventListener = function(container) {
        $(container)[0].addEventListener('input', function(e) {
            autoUppercase(e.target);
            parser(e.target);
        });
    };

    var addAssistantButton = function(input) {
        var $input = $(input);
        if ($input.siblings('.edtf-assistant-button').length) {
            return;
        }
        var translate = function(s) {
            return (typeof Omeka !== 'undefined' && Omeka.jsTranslate) ? Omeka.jsTranslate(s) : s;
        };
        var label = translate('Assistant for Extended Date/Time Format');
        var $btn = $('<button type="button" class="edtf-assistant-button" aria-label="' + label + '" title="' + label + '">'
            + '<span class="o-icon-edit"></span></button>');
        $input.before($btn);
    };

    var listen = function() {
        // Delegated click handler for assistant buttons (works for cloned inputs).
        $(document).off('click.edtfAssistant').on('click.edtfAssistant', '.edtf-assistant-button', function(e) {
            e.preventDefault();
            var input = $(this).siblings('input.edtf-value')[0];
            if (input) {
                openAssistant(input, this);
            }
        });

        // Toggle raw ↔ humanized display on the valid container.
        $(document).off('click.edtfToggleView').on('click.edtfToggleView', '.edtf-toggle-view', function(e) {
            e.preventDefault();
            var $container = $(this).closest('.valid-string-container');
            var view = $container.attr('data-view') === 'human' ? 'raw' : 'human';
            $container.attr('data-view', view);
            $container.find('.edtf-display-value').text($container.attr(view === 'human' ? 'data-human' : 'data-raw'));
        });

        $(document).on('o:prepare-value o:prepare-value-annotation', function(e, type, container) {
            if ('edtf' === type) {
                addParserEventListener(container);
                var input = container.find ? container.find('input.edtf-value')[0] : null;
                if (input) {
                    addAssistantButton(input);
                }
            }
        });

        var inputs = document.querySelectorAll('.edtf input.edtf-value');
        inputs.forEach(function(input) {
            parser(input);
            addParserEventListener(input);
            addAssistantButton(input);
        });
    };

    return {
        listen: listen
    };
})(jQuery);
