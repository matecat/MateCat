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
import replaceOccurrences from "./replaceOccurrences";
import {
	getXliffRegExpression,
	getIdAttributeRegEx,
	cleanSegmentString,
	unescapeHTML,
	unescapeHTMLLeaveTags,
	decodePhTags,
	formatText
} from "./textUtils"

import buildFragmentFromText from "./buildFragmentFromText";
import insertText from "./insertText";
import updateEntityData from "./updateEntityData";
import tagFromEntity from "./tagFromEntity";
import matchTagInEditor from "./matchTagInEditor";
import getSelectedText from "./getSelectedText";
import addTagEntityToEditor from "./addTagEntityToEditor";
import canDecorateRange from "./canDecorateRange";


const DraftMatecatUtils = {
	// Text utils
	cleanSegmentString,
	getXliffRegExpression,
	getIdAttributeRegEx,
	unescapeHTML,
	unescapeHTMLLeaveTags,
	formatText,
	// Tag Utils
	matchTag,
	decodeTagInfo,
	tagFromEntity,
	// Entity Utils
	getEntities,
	createNewEntitiesFromMap,
	linkEntities,
	beautifyEntities,
	applyEntityToContentBlock,
	updateEntityData,
	matchTagInEditor,
	addTagEntityToEditor,
	canDecorateRange,
	// Fragment Utils
	insertFragment,
	duplicateFragment,
	getEntitiesInFragment,
	buildFragmentFromText,
	// Segment Utils
	encodeContent,
	decodeSegment,
	replaceOccurrences,
	decodePhTags,
	// General
	insertText,
	getSelectedText
};

module.exports = DraftMatecatUtils;
