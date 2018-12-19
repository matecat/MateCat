/*
	Component: ui.concordance
 */
$( document ).on( 'sse:concordance', function ( ev, message ) {
    SegmentActions.setConcordanceResult(message.data.id_segment, message.data);
} );
$.extend(UI, {
	openConcordance: function(currentSelectedText,currentSearchInTarget) {
		SegmentActions.activateTab(UI.getSegmentId(UI.currentSegment), 'concordances');
        SegmentActions.findConcordance(UI.getSegmentId(UI.currentSegment), {text: currentSelectedText, inTarget: currentSearchInTarget});
	},

	preOpenConcordance: function() {
		var selection = window.getSelection();
		if (selection.type == 'Range') { // something is selected
			var isSource = $(selection.baseNode.parentElement).hasClass('source');
			var str = selection.toString().trim();
			if (str.length) { // the trimmed string is not empty
				var currentSearchInTarget = (isSource) ? 0 : 1;
				this.openConcordance(str,currentSearchInTarget);
			}
		}
	},

});


