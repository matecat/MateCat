'use strict';

import getEntities from "./getEntities";
import matchTag from "./matchTag";
import encodeContent from "./encodeContent";
import decodeSegment from "./decodeSegment";
import duplicateFragment from "./duplicateFragment";
import applyEntityToContentBlock from "./applyEntityToContentBlock";
import insertFragment from "./insertFragment";
import getEntitiesInFragment from "./getEntitiesInFragment";
import createNewEntitiesFromMap from "./createNewEntitiesFromMap";
import linkEntities from "./linkEntities";
import beautifyEntities from "./beautifyEntities";
import decodeTagInfo from "./decodeTagInfo";
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
	decodeTagInfo,
	// Entity Utils
	getEntities,
	createNewEntitiesFromMap,
	linkEntities,
	beautifyEntities,
	applyEntityToContentBlock,
	// Fragment Utils
	insertFragment,
	duplicateFragment,
	getEntitiesInFragment,
	// Segment Utils
	encodeContent,
	decodeSegment,

};

module.exports = DraftMatecatUtils;
