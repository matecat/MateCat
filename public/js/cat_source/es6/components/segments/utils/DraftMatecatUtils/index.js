'use strict';

import getEntities from "./getEntities";
import matchTag from "./matchTag";
import encodeContent from "./encodeContent";
import decodeSegment from "./decodeSegment";
import duplicateFragment from "./duplicateFragment";
import applyEntityToContentBlock from "./applyEntityToContentBlock";
import insertFragment from "./insertFragment";
import {
	getXliffRegExpression,
	cleanSegmentString,
	unescapeHTML,
	unescapeHTMLLeaveTags
} from "./textUtils"

const DraftMatecatUtils = {
	// Text utils
	cleanSegmentString,
	getXliffRegExpression,
	unescapeHTML,
	unescapeHTMLLeaveTags,
	// Tag Utils
	matchTag,
	getEntities,
	encodeContent,
	decodeSegment,
	// Entity Utils
	duplicateFragment,
	applyEntityToContentBlock,
	insertFragment
};

module.exports = DraftMatecatUtils;
