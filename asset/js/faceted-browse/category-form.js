$(document).ready(function() {

/**
 * Add all available EDTF values to the FacetedBrowse form.
 */
const edtfAddAll = function(textareaId) {
    const textarea = $(textareaId);
    const rows = $('#show-all-table').data('rows');
    const container = $('.confirm-main');
    const labels = [];
    $.each(rows, (index, row) => {
        labels.push(row.label);
    });
    textarea.val(labels.join("\n"));
    container.animate({
        scrollTop: textarea.closest('.field').offset().top - container.offset().top + container.scrollTop()
    });
};

// Handle add all button.
$(document).on('click', '#add-all', function(e) {
    switch ($('#facet-type-input').val()) {
        case 'edtf_after':
            edtfAddAll('#date-after-values');
            break;
        case 'edtf_before':
            edtfAddAll('#date-before-values');
            break;
        case 'edtf_in_interval':
            edtfAddAll('#date-in-interval-values');
            break;
    }
});

});
