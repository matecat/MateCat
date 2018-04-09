/*
	Component: ui.concordance
 */
$.extend(UI, {
	openConcordance: function() {
		SegmentActions.activateTab(UI.getSegmentId(UI.currentSegment), 'concordances');
        SegmentActions.findConcordance(UI.getSegmentId(UI.currentSegment), {text: this.currentSelectedText, inTarget: this.currentSearchInTarget});
	},

	preOpenConcordance: function() {
		var selection = window.getSelection();
		if (selection.type == 'Range') { // something is selected
			var isSource = $(selection.baseNode.parentElement).hasClass('source');
			var str = selection.toString().trim();
			if (str.length) { // the trimmed string is not empty
				this.currentSelectedText = str;
				this.currentSearchInTarget = (isSource) ? 0 : 1;
				this.openConcordance();
			}
		}
	},

});


