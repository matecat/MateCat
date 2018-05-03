/*
	Component: ui.concordance
 */
$.extend(UI, {
	openConcordance: function(currentSelectedText,currentSearchInTarget) {
		SegmentActions.activateTab(UI.getSegmentId(UI.currentSegment), 'concordances');
        SegmentActions.findConcordance(UI.getSegmentId(UI.currentSegment), {text: currentSelectedText, inTarget: currentSearchInTarget});
	},

	preOpenConcordance: function() {
		let selection = window.getSelection();
		if (selection.type == 'Range') { // something is selected
			let isSource = $(selection.baseNode.parentElement).hasClass('source');
			let str = selection.toString().trim();
			if (str.length) { // the trimmed string is not empty
				let currentSearchInTarget = (isSource) ? 0 : 1;
				this.openConcordance(str,currentSearchInTarget);
			}
		}
	},

});


