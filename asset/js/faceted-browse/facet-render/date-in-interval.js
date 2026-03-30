FacetedBrowse.registerFacetApplyStateHandler('edtf_in_interval', function(facet, facetState) {
    const thisFacet = $(facet);
    thisFacet.find(`select.date-in-interval-value`).val(facetState);
});

$(document).ready(function() {

const container = $('#container');

container.on('change', '.date-in-interval-value', function(e) {
    const thisSelect = $(this);
    const facet = thisSelect.closest('.facet');
    const facetData = facet.data('facetData');
    // "Date in interval X" means the item's EDTF range is contained within the
    // period X (e.g., contained within the year 1984).
    // Expressed as: value_min >= X_start AND value_max <= X_end.
    const val = thisSelect.val();
    const encoded = encodeURIComponent(val);
    const pid = facetData.property_id;
    const query = val
        ? `edtf[gte][pid]=${pid}&edtf[gte][val]=${encoded}&edtf[lte][pid]=${pid}&edtf[lte][val]=${encoded}`
        : '';
    FacetedBrowse.setFacetState(facet.data('facetId'), thisSelect.val(), query);
    FacetedBrowse.triggerStateChange();
});

});
