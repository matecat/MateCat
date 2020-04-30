'use strict';

import {
	getEntities,
	duplicateFragment,
	applyEntityToContentBlock,
	cleanSegmentString,
	matchTag,
	encodeContent,
	unescapeHTMLLeaveTags
} from './ContentEncoder'

const DraftMatecatUtils = {
	getEntities,
	duplicateFragment,
	applyEntityToContentBlock,
	cleanSegmentString,
	matchTag,
	encodeContent,
	unescapeHTMLLeaveTags
};


module.exports = DraftMatecatUtils;
