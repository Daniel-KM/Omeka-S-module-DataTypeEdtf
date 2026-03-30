/**
 * EDTF Data Type: real-time validation for EDTF input fields.
 *
 * Requires edtf.js (window.edtf.parse) and jQuery.
 */
var EdtfDataType = (function($) {
    'use strict';

    var parser = function(container) {
        var outputString = '';
        var shortExplanation = '';
        var caretLocation, caretOffset = 0;

        try {
            edtf.parse(container.value);
            $(container).closest('.edtf').find('.invalid-value').empty();
            var validString =
                '<div class="valid-string-container">' +
                    '<span class="o-icon-edit icon" title="Correct value" aria-label="accepted value"></span>' +
                    '<span class="valuesuggest-id">' + container.value + '</span>' +
                '</div>';
            var validStringContainer = $(container).closest('.edtf').find('.valid-string-container');
            if (validStringContainer.length > 0) {
                $(validStringContainer).replaceWith(validString);
            } else {
                $(container).closest('.edtf').prepend(validString);
            }
        } catch (e) {
            var message = String(e.message);
            var lines = message.split('\n');
            lines.forEach(function(line, i) {
                if (/Unexpected/.test(line)) {
                    shortExplanation = line.substring(0, line.indexOf('.'));
                } else if (/Syntax/.test(line)) {
                    outputString = lines[i + 2].split(' ')[1];
                    caretOffset = lines[i + 2].split(' ')[0].length + 1;
                } else if (/\^/.test(line)) {
                    caretLocation = line.indexOf('^') - caretOffset;
                }
            });

            if (outputString.length > 0) {
                outputString =
                    '<div><p class="outputstring">' +
                    outputString.substring(0, caretLocation) +
                    '<span class="caret">' + outputString.substring(caretLocation, caretLocation + 1) + '</span>' +
                    outputString.substring(caretLocation + 1) +
                    ' [' + shortExplanation + ']' +
                    '</p></div>';
            }

            $(container).closest('.edtf').find('.invalid-value').html(outputString);
            $(container).closest('.edtf').find('.valid-string-container').remove();
        }
    };

    var addParserEventListener = function(container) {
        $(container)[0].addEventListener('input', function(e) {
            parser(e.target);
        });
    };

    var listen = function() {
        $(document).on('o:prepare-value o:prepare-value-annotation', function(e, type, container) {
            if ('edtf:date' === type) {
                addParserEventListener(container);
            }
        });

        var inputs = document.querySelectorAll('.edtf input.edtf-value');
        inputs.forEach(function(input) {
            parser(input);
            addParserEventListener(input);
        });
    };

    return {
        listen: listen
    };
})(jQuery);
