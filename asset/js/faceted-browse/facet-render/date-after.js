FacetedBrowse.registerFacetApplyStateHandler('edtf_after', function(facet, facetState) {
    const thisFacet = $(facet);
    thisFacet.find(`select.date-after-value`).val(facetState);
});

$(document).ready(function() {

const container = $('#container');

container.on('change', '.date-after-value', function(e) {
    const thisSelect = $(this);
    const facet = thisSelect.closest('.facet');
    const facetData = facet.data('facetData');
    const query = thisSelect.val()
        ? `edtf[gte][pid]=${facetData.property_id}&edtf[gte][val]=${encodeURIComponent(thisSelect.val())}`
        : '';
    FacetedBrowse.setFacetState(facet.data('facetId'), thisSelect.val(), query);
    FacetedBrowse.triggerStateChange();
});

});
