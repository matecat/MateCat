import {
  EditorState,
  Modifier,
  SelectionState,
  ContentState,
  CharacterMetadata,
  BlockMapBuilder,
  CompositeDecorator,
} from 'draft-js'

import _ from 'lodash'
import SearchHighlight from '../../SearchHighLight/SearchHighLight.component'
import GlossaryComponent from '../../GlossaryComponents/GlossaryHighlight.component'
import QaCheckGlossaryHighlight from '../../GlossaryComponents/QaCheckGlossaryHighlight.component'
import QaCheckBlacklistHighlight from '../../GlossaryComponents/QaCheckBlacklistHighlight.component'
import LexiqaHighlight from '../../LexiqaHighlight/LexiqaHighlight.component'
import CompoundDecorator from '../CompoundDecorator'

// export const tagStruct = {
//     'ph': {
//         type: 'ph',
//         openRegex: /&lt;ph/g,
//         openLength: 6,
//         closeRegex: /(\/&gt;)/, // '/>'
//         selfClosing: true,
//         isClosure: false,
//         placeholder: null,
//         placeholderRegex: /equiv-text="base64:(.+)"/,
//         decodeNeeded: true
//     },
//     'g': {
//         type: 'g',
//         openRegex: /&lt;g/g,
//         openLength: 5,
//         closeRegex: /(&gt;)/, // '>'
//         selfClosing: false,
//         isClosure: false,
//         placeholder: null,
//         placeholderRegex: /(id="\w+")/,
//         decodeNeeded: false
//     },
//     'cl': {
//         type: 'cl',
//         openRegex: /&lt;\/g&gt;/g,
//         openLength: 10,
//         closeRegex: null,
//         selfClosing: false,
//         isClosure: true,
//         placeholder: '<g/>',
//         placeholderRegex: null,
//         decodeNeeded: false
//     },
//     'nbsp':{
//         type: 'nbsp',
//         openRegex: /##\$(_A0)\$##/g,
//         openLength: 9,
//         closeRegex: null,
//         selfClosing: true,
//         isClosure: false,
//         placeholder: '°',
//         placeholderRegex: null,
//         decodeNeeded: false
//     },
//     'tab':{
//         type: 'tab',
//         openRegex: /##\$(_09)\$##/g,
//         openLength: 9,
//         closeRegex: null,
//         selfClosing: true,
//         isClosure: false,
//         placeholder: '#',
//         placeholderRegex: null,
//         decodeNeeded: false
//     },
//     'lineFeed':{
//         type: 'lineFeed',
//         openRegex: /##\$(_0D)\$##/g,
//         openLength: 9,
//         closeRegex: null,
//         selfClosing: true,
//         isClosure: false,
//         placeholder: '\\n',
//         placeholderRegex: null,
//         decodeNeeded: false
//     }
// };
// /**
//  *
//  * @param tag
//  * @returns {string} decodedTagData - Decoded data inside tag
//  */
// export const decodeTagInfo = (tag) => {
//     let decodedTagData;
//     if(tag.type in tagStruct) {
//         // If regex exists, try to search, else put placeholder
//         if(tagStruct[tag.type].placeholderRegex!== null){
//             const idMatch = tagStruct[tag.type].placeholderRegex.exec(tag.data.originalText);
//             if(idMatch && idMatch.length > 1) {
//                 decodedTagData =  tagStruct[tag.type].decodeNeeded ? atob(idMatch[1]) : idMatch[1];
//                 decodedTagData = unescapeHTML(decodedTagData);
//             }else if(tagStruct[tag.type].placeholder){
//                 decodedTagData = tagStruct[tag.type].placeholder;
//             }
//         }else {
//             decodedTagData = tagStruct[tag.type].placeholder;
//         }
//     }else{
//         decodedTagData = '<unknown/>'
//     }
//     return decodedTagData;
// };
// /**
//  *
//  * @param escapedHTML
//  * @returns {string}
//  */
// export const unescapeHTML = (escapedHTML) => {
//     return escapedHTML
//         .replace(/&lt;/g,'<')
//         .replace(/&gt;/g,'>')
//         .replace(/&amp;/g,'&')
//         .replace(/&apos;/g,'\'')
//         .replace(/&quot;/g,'\"');
// };
//
// /**
//  *
//  * @param escapedHTML
//  * @returns {string}
//  */
// export const unescapeHTMLLeaveTags = (escapedHTML) => {
//     return escapedHTML.replace(/&amp;/g,'&').replace(/&apos;/g,'\'').replace(/&quot;/g,'\"');
// };
//
// /**
//  *
//  * @param editorState - current editor state, can be empty
//  * @param plainText - text where each entity applies
//  * @returns {ContentState}  contentState - A ContentState with each tag as an entity
//  */
// export const createNewEntitiesFromMap = (editorState, plainText = '') => {
//     let contentState = editorState.getCurrentContent();
//     // If editor content is empty, create new content from plainText
//     if(!contentState.hasText() || plainText !== ''){
//         contentState = ContentState.createFromText(plainText);
//     }
//     // Compute tag range
//     const tagRangeFromPlainText = matchTag(contentState.getPlainText());
//     // Apply each entity to the block where it belongs
//     const blocks = contentState.getBlockMap();
//     let maxCharsInBlocks = 0;
//     blocks.forEach((contentBlock) => {
//         maxCharsInBlocks += contentBlock.getLength();
//         tagRangeFromPlainText.forEach( tag =>{
//             if (tag.offset < maxCharsInBlocks &&
//                 (tag.offset + tag.length) <= maxCharsInBlocks &&
//                 tag.offset >= (maxCharsInBlocks - contentBlock.getLength())) {
//                 // Clone tag
//                 const tagEntity = {...tag};
//                 // Each block start with offset = 0 so we have to adapt selection
//                 const selectionState = new SelectionState({
//                     anchorKey: contentBlock.getKey(),
//                     anchorOffset: (tag.offset - (maxCharsInBlocks - contentBlock.getLength())),
//                     focusKey: contentBlock.getKey(),
//                     focusOffset: ((tag.offset + tag.length) - (maxCharsInBlocks - contentBlock.getLength()))
//                 });
//                 // Decode tag data and place them cleaned inside tag object
//                 tagEntity.data.placeHolder = decodeTagInfo(tagEntity);
//                 // Create entity
//                 const {type, mutability, data} = tagEntity;
//                 const contentStateWithEntity = contentState.createEntity(type, mutability, data);
//                 const entityKey = contentStateWithEntity.getLastCreatedEntityKey();
//                 // Apply entity on the previous selection
//                 contentState = Modifier.applyEntity(
//                     contentState,
//                     selectionState,
//                     entityKey
//                 );
//             }
//         });
//     });
//
//     return contentState
// };
// /**
//  *
//  * @param editorState
//  * @returns {ContentState} - A ContentState in which each tag that is not self-closable, is linked to another tag
//  */
// export const linkEntities  = (editorState) => {
//     let contentState = editorState.getCurrentContent();
//
//     const openEntities = getEntities(editorState).filter( entityObj => {
//         if ( entityObj.entity.data.hasOwnProperty('closeTagId') && entityObj.entity.data.closeTagId) {
//             return entityObj;
//         }
//     });
//     const closeEntities = getEntities(editorState).filter( entityObj => {
//         if (entityObj.entity.data.hasOwnProperty('openTagId') && entityObj.entity.data.openTagId) {
//             return entityObj;
//         }
//     });
//     openEntities.forEach( ent => {
//         const closure = closeEntities.filter(entObj => {
//             if(entObj.entity.data.openTagId === ent.entity.data.closeTagId){
//                 return entObj;
//             }
//         });
//         if(closure.length > 0) {
//             contentState = contentState.mergeEntityData( closure[0].entityKey, {openTagKey: ent.entityKey} );
//             contentState = contentState.mergeEntityData( ent.entityKey, {closeTagKey: closure[0].entityKey} );
//         }
//     });
//     return contentState;
// };
// /**
//  *
//  * @param editorState
//  * @returns {ContentState} contentState - A a new ContentState in which entities are displayed as placeholder
//  */
// export const beautifyEntities  = (editorState) => {
//
//     const inlineStyle = editorState.getCurrentInlineStyle();
//     const entities = getEntities(editorState); //start - end
//     const entityKeys =  entities.map( entity => entity.entityKey);
//
//     let contentState = editorState.getCurrentContent();
//     let editorStateClone = editorState;
//
//     entityKeys.forEach( key => {
//         // Update entities and blocks cause previous cycle updated offsets
//         // LAZY NOTE: entity.start and entity.end are block-based
//         let entitiesInEditor = getEntities(editorStateClone);
//         // Filter only looped tag and get data
//         // Todo: add check on tag array length
//         const tagEntity = entitiesInEditor.filter( entity => entity.entityKey === key)[0];
//         const {placeHolder} = tagEntity.entity.data;
//         // Get block-based selection
//         const selectionState = new SelectionState({
//             anchorKey: tagEntity.blockKey,
//             anchorOffset: tagEntity.start,
//             focusKey: tagEntity.blockKey,
//             focusOffset: tagEntity.end
//         });
//         // Replace text of entity with placeholder
//         contentState = Modifier.replaceText(
//             contentState,
//             selectionState,
//             placeHolder,
//             inlineStyle,
//             tagEntity.entityKey
//         );
//         // Update contentState
//         editorStateClone = EditorState.set(editorStateClone, {currentContent: contentState});
//     });
//     // Todo verificare se serve il push dell'editor state
//     return contentState;
// };
// /**
//  *
//  * @param editorState
//  * @param [entityType]
//  * @returns {[]} An array of entities with each entity position
//  */
// export const getEntities = (editorState, entityType = null) => {
//     const content = editorState.getCurrentContent();
//     const entities = [];
//     content.getBlocksAsArray().forEach((block) => {
//         let selectedEntity = null;
//         block.findEntityRanges(
//             (character) => {
//                 if (character.getEntity() !== null) {
//                     const entity = content.getEntity(character.getEntity());
//                     if (!entityType || (entityType && entity.getType() === entityType)) {
//                         selectedEntity = {
//                             entityKey: character.getEntity(),
//                             blockKey: block.getKey(),
//                             entity: content.getEntity(character.getEntity()),
//                         };
//                         return true;
//                     }
//                 }
//                 return false;
//             },
//             (start, end) => {
//                 entities.push({...selectedEntity, start, end});
//             });
//     });
//     // LAZY NOTE: returned entity.start and entity.end are block-based offsets
//     return entities;
// };
// export const generateBlocksForRaw = (originalContent, entitySet) => {
//     let blocks = [];
//     let entityRanges = [];
//     entitySet.forEach( ({offset, length, key}) => {
//         entityRanges.push({offset, length, key})
//     });
//
//     blocks.push({
//         text: originalContent,
//         type: 'unstyled',
//         entityRanges: entityRanges
//     });
//
//     return blocks;
// };
// export const generateEntityMapForRaw = (originalContent, entitySet) => {
//     let entityMap = {};
//     console.log('Set: ', entitySet)
//     entitySet.forEach( ({key, type, mutability, data}) => {
//         entityMap[key] = {
//             type: type,
//             mutability: mutability,
//             data: data
//         };
//         console.log('Added: ', entityMap[key])
//     });
//     return entityMap;
// };
//
// export const replaceEntityText = (entity, editorState) => {
//     const contentState = editorState.getCurrentContent();
//     const selectionState = editorState.getSelection().merge({
//         anchorOffset: entity.offset,
//         focusOffset: entity.offset + entity.length
//     });
//     console.log('Selezione: ',selectionState);
//     const replacedText = Modifier.replaceText(contentState, selectionState, '&lt;ph ');
//     console.log(replacedText);
//
//     const newEditorState = EditorState.push(
//         editorState,
//         replacedText,
//         'insert-characters'
//     );
//
//     return newEditorState;
// };
// /**
//  *
//  * @param editorState
//  * @param plainText - text to analyze when editor is empty
//  * @returns {*|EditorState} editorStateModified - An EditorState with all known tags treated as entities
//  */
// export const encodeContent = (editorState, plainText = '') => {
//
//     // Create entities
//     let newContent = createNewEntitiesFromMap(editorState, plainText);
//     // Apply entities to EditorState
//     let editorStateModified = EditorState.push(editorState, newContent, 'apply-entity');
//     // Link each openTag with its closure using entity key, otherwise tag are linked with openTagId/closeTagId
//     newContent = linkEntities(editorStateModified);
//     editorStateModified = EditorState.push(editorState, newContent, 'change-block-data');
//     // Replace each tag text with a placeholder
//     newContent = beautifyEntities(editorStateModified);
//     editorStateModified = EditorState.push(editorState, newContent, 'change-block-data');
//     console.log(getEntities(editorStateModified));
//     return editorStateModified;
// };
// /**
//  *
//  * @param plainContent
//  * @returns {*[]} array of all tag occurrences in plainContent
//  */
// export const matchTag = (plainContent) => {
//
//     //findWithRegexV4(plainContent, tagRegex['ph']);
//
//     // Escape line feed or it counts as 1 position that disappear when you create the ContentBlock
//     const plainContentLineFeedEscaped = plainContent.replace(/\n/g,'');
//
//     // STEP 1 - Find all opening and save offset
//     let tagMap;
//     let openTags = [];
//     for (let key in tagStruct) {
//         if(!tagStruct[key].selfClosing && !tagStruct[key].isClosure){
//             tagMap = findWithRegexV4(plainContentLineFeedEscaped, tagStruct[key]);
//             openTags = [...openTags, ...tagMap]
//         }
//     }
//     console.log('Openings: ', openTags);
//
//     // STEP 2 - Find all closing and save offset
//     let closingTags = [];
//     for (let key in tagStruct) {
//         if(tagStruct[key].isClosure){
//             tagMap = findWithRegexV4(plainContentLineFeedEscaped, tagStruct[key]);
//             closingTags = [...closingTags, ...tagMap]
//         }
//     }
//
//     console.log('Closures: ', closingTags);
//
//     // STEP 3 - Find all self-closing tag and save offset
//     let selfClosingTags = [];
//     for (let key in tagStruct) {
//         if(tagStruct[key].selfClosing){
//             tagMap = findWithRegexV4(plainContentLineFeedEscaped, tagStruct[key]);
//             selfClosingTags = [...selfClosingTags, ...tagMap]
//         }
//     }
//     console.log('Self-closing: ', selfClosingTags);
//
//     // STEP 4 - Sort arrays by offset
//     openTags.sort((a, b) => {return b.offset-a.offset});
//     closingTags.sort((a, b) => {return a.offset-b.offset});
//
//     // STEP 5 - Matching non self-closing with each closure
//     // Assuming that closure is the same for every tag: '</>'
//     closingTags.forEach( closingTag => {
//         let i = 0, notFound = true;
//         while(i < openTags.length && notFound) {
//             if(closingTag.offset > openTags[i].offset && !openTags[i].data.closeTagId ){
//                 notFound = !notFound;
//                 const uniqueId = openTags[i].offset + '-' + closingTag.offset;
//                 openTags[i].data.closeTagId = uniqueId;
//                 closingTag.data.openTagId = uniqueId;
//
//             }
//             i++;
//         }
//     });
//     return [...openTags, ...closingTags, ...selfClosingTags];
// };
// /**
//  *
//  * @param text
//  * @param tagSignature
//  * @returns {[]} tagRange - array with all occurrences of tagSignature in the input text
//  */
// export const findWithRegexV4 = (text, tagSignature) => {
//     let matchArr;
//     let entity = {
//         offset: -1,
//         length: null,
//         type: null
//     };
//     const {type, openRegex, openLength, closeRegex} = tagSignature;
//     const tagRange = [];
//
//     // Todo: remove loop safelock after test
//     let safelock = 0; // Never bet on a while loop
//     console.log('Searching for: ', type);
//     while((matchArr = openRegex.exec(text)) !== null && safelock < 100){
//         safelock = safelock +1;
//         entity.offset = matchArr.index;
//         if(!closeRegex) {
//             entity.length = openLength;
//             let originalText = text.slice(entity.offset, entity.offset + entity.length);
//             entity.data = {'originalText': originalText, 'openTagId': null, 'openTagKey': null};
//         }else {
//             let slicedText = text.slice(entity.offset, text.length);
//             matchArr = closeRegex.exec(slicedText);
//             entity.length = matchArr.index + matchArr[1].length; //Length of previous regex
//             let originalText = text.slice(entity.offset, entity.offset + entity.length);
//             entity.data = {'originalText': originalText, 'closeTagId': null, 'closeTagKey': null};
//         }
//         entity.type = type;
//         entity.mutability = 'IMMUTABLE';
//         tagRange.push({...entity});
//     }
//     console.log('Tag range: ', tagRange);
//     return tagRange;
// };
// /**
//  *
//  * @param editorState
//  * @returns {string}
//  */
// export const decodeSegment  = (editorState) => {
//
//     const inlineStyle = editorState.getCurrentInlineStyle();
//     const entities = getEntities(editorState); //start - end
//     const entityKeys =  entities.map( entity => entity.entityKey);
//
//     let contentState = editorState.getCurrentContent();
//     let editorStateClone = editorState;
//
//     entityKeys.forEach( key => {
//         // Update entities and blocks cause previous cycle updated offsets
//         // LAZY NOTE: entity.start and entity.end are block-based
//         let entitiesInEditor = getEntities(editorStateClone);
//         // Filter only looped tag and get data
//         // Todo: add check on tag array length
//         const tagEntity = entitiesInEditor.filter( entity => entity.entityKey === key)[0];
//         const {originalText} = tagEntity.entity.data;
//         // Get block-based selection
//         const selectionState = new SelectionState({
//             anchorKey: tagEntity.blockKey,
//             anchorOffset: tagEntity.start,
//             focusKey: tagEntity.blockKey,
//             focusOffset: tagEntity.end
//         });
//         // Replace text of entity with original text and delete entity key
//         contentState = Modifier.replaceText(
//             contentState,
//             selectionState,
//             originalText,
//             inlineStyle,
//             null
//         );
//         // Update contentState
//         editorStateClone = EditorState.set(editorStateClone, {currentContent: contentState});
//     });
//     return contentState.getPlainText();
// };
// /**
//  * Remove all tags except for: nbsp, tab, softReturn
//  * @param segmentString
//  * @returns {*|void|string}
//  */
// export const cleanSegmentString = (segmentString) => {
//     const regExp = getXliffRegExpression();
//     return segmentString.replace(regExp, '');
// };
// export const getXliffRegExpression = () => {
//     return /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gmi; // group, multiline, case-insensitive
// };
// // Utilities for No-Merge
// export const insertFragment = (editorState, fragment) => {
//
//     let newContent = Modifier.replaceWithFragment(
//         editorState.getCurrentContent(),
//         editorState.getSelection(),
//         fragment
//     );
//     return EditorState.push(
//         editorState,
//         newContent,
//         'insert-fragment'
//     );
// };
//
// export const applyEntityToContentBlock = (contentBlock, start, end, entityKey) => {
//     var characterList = contentBlock.getCharacterList();
//     while (start < end) {
//         characterList = characterList.set(
//             start,
//             CharacterMetadata.applyEntity(characterList.get(start), entityKey)
//         );
//         start++;
//     }
//     return contentBlock.set('characterList', characterList);
// };
//
// export const getEntitiesInFragment = (fragment, editorState) => {
//     const contentState = editorState.getCurrentContent();
//     const entities = {};
//     fragment.forEach(block => {
//         block.getCharacterList().forEach(character => {
//             if (character.entity) {
//                 entities[character.entity] = contentState.getEntity(character.entity)
//             }
//         });
//     });
//     return entities;
// };

//Search
/*
export const activateSearch = (editorState, decoratorStructure, text, params, occurrencesInSegment, currentIndex, tagRange) => {

    const generateSearchDecorator = (highlightTerm, occurrences, params, currentIndex, tagRange) => {
        let regex = SearchUtils.getSearchRegExp(highlightTerm, params.ingnoreCase, params.exactMatch);
        return {
            name: 'search',
            strategy: (contentBlock, callback) => {
                if (highlightTerm !== '') {
                    findWithRegex(regex, contentBlock, occurrences, tagRange, callback);
                }
            },
            component: SearchHighlight,
            props: {
                occurrences,
                currentIndex,
                tagRange
            }
        };
    };

    const findWithRegex = (regex, contentBlock, occurrences, tagRange, callback) => {
        const text = contentBlock.getText();
        let matchArr, start, end;
        let index = 0;
        while ((matchArr = regex.exec(text)) !== null) {
            start = matchArr.index;
            end = start + matchArr[0].length;
            if ( occurrences[index] ) {
                occurrences[index].start = start;
            }
            !isTag(start, tagRange) && callback(start, end);
            index++;
        }
    };
    const isTag = (start, tagRange) => {
        let indexToAdd = 0;
        //Note: The list of tags contains the indexes calculated with the ph tags in the text, while the list of occurrences does not.
        var tag = tagRange.find((item)=>{
            let isTag = start + indexToAdd >= item.offset && start + indexToAdd <= item.offset + item.length   ;
            indexToAdd += item.length;
            return isTag;
        });
        return !!tag;
    };
    let search = text;
    let decorators = decoratorStructure.slice();
    let occurrencesClone = _.cloneDeep(occurrencesInSegment);
    _.remove(decorators, (decorator) => decorator.name === 'search');
    decorators.push( generateSearchDecorator( search, occurrencesClone, params, currentIndex, tagRange) );
    // const newDecorator = new CompositeDecorator( decorators );
    const newDecorator = new CompoundDecorator( decorators );
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};

//Glossary
export const activateGlossary = (editorState, decoratorStructure, glossary, text, sid) => {

    const generateGlossaryDecorator = (regex, sid) => {
        return {
            name: 'glossary',
            strategy: (contentBlock, callback, contentState) => {
                if ( regex !== '') {
                    findWithRegex(regex,contentState, contentBlock, callback, 'glossary');
                }
            },
            component: GlossaryComponent,
            props: {
                sid: sid
            }
        };
    };

    const findWithRegex = (regex, contentState, contentBlock, callback, decoratorName) => {
        const text = contentBlock.getText();
        let matchArr, start, end;
        while ((matchArr = regex.exec(text)) !== null) {
            start = matchArr.index;
            end = start + matchArr[0].length;
            const canDecorate = DraftMatecatUtils.canDecorateRange(start, end, contentBlock, contentState, decoratorName);
            if(canDecorate) callback(start, end);
            //callback(start, end);
        }
    };

    const createGlossaryRegex = (glossaryObj, text) => {
        const matches = _.map(glossaryObj, ( elem ) => (elem[0].raw_segment) ? elem[0].raw_segment: elem[0].segment);
        let re;
        try {
            re = new RegExp( '\\b(' + matches.join('|') + ')\\b', "gi" );
            //If source languace is Cyrillic or CJK
            if ( config.isCJK) {
                re = new RegExp( '(' + matches.join('|') + ')', "gi" );
            }
        } catch ( e ) {
            return null;
        }
        return re;
    };
    console.log("Add Glossary Decorator: ", sid,  glossary);

    let decorators = decoratorStructure.slice();
    const regex = createGlossaryRegex(glossary, text);
    _.remove(decorators, (decorator) => decorator.name === 'glossary');
    decorators.push( generateGlossaryDecorator( regex, sid ) );
    // const newDecorator = new CompositeDecorator( decorators );
    const newDecorator = new CompoundDecorator( decorators );
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};

//Qa check Glossary
export const activateQaCheckGlossary = (editorState, decoratorStructure, qaCheckGlossary, text, sid) => {

    const generateGlossaryDecorator = (regex, sid) => {
        return {
            name: 'qaCheckGlossary',
            strategy: (contentBlock, callback) => {
                if ( regex !== '') {
                    findWithRegex(regex, contentBlock, callback);
                }
            },
            component: QaCheckGlossaryHighlight
        };
    };

    const findWithRegex = (regex, contentBlock, callback) => {
        const text = contentBlock.getText();
        let matchArr, start, end;
        while ((matchArr = regex.exec(text)) !== null) {
            start = matchArr.index;
            end = start + matchArr[0].length;
            callback(start, end);
        }
    };

    const createGlossaryRegex = (glossaryArray) => {
        const matches = _.map(glossaryArray, ( elem ) => (elem.raw_segment) ? elem.raw_segment: elem.segment);
        let re;
        try {
            re = new RegExp( '\\b(' + matches.join('|') + ')\\b', "gi" );
            //If source languace is Cyrillic or CJK
            if ( config.isCJK) {
                re = new RegExp( '(' + matches.join('|') + ')', "gi" );
            }
        } catch ( e ) {
            return null;
        }
        return re;
    };
    // console.log("Add Glossary check Decorator: ", sid,  qaCheckGlossary);

    let decorators = decoratorStructure.slice();
    const regex = createGlossaryRegex(qaCheckGlossary);
    _.remove(decorators, (decorator) => decorator.name === 'qaCheckGlossary');
    decorators.push( generateGlossaryDecorator( regex, sid ) );
    const newDecorator = new CompoundDecorator( decorators );
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};

//Qa check Blacklist
export const activateQaCheckBlacklist = (editorState, decoratorStructure, qaCheckGlossary) => {

    const generateGlossaryDecorator = (regex) => {
        return {
            name: 'qaCheckBlacklist',
            strategy: (contentBlock, callback) => {
                if ( regex !== '') {
                    findWithRegex(regex, contentBlock, callback);
                }
            },
            component: QaCheckBlacklistHighlight
        };
    };

    const findWithRegex = (regex, contentBlock, callback) => {
        const text = contentBlock.getText();
        let matchArr, start, end;
        while ((matchArr = regex.exec(text)) !== null) {
            start = matchArr.index;
            end = start + matchArr[0].length;
            callback(start, end);
        }
    };

    const createGlossaryRegex = (glossaryArray) => {
        let re;
        try {
            re = new RegExp( '\\b(' + glossaryArray.join('|') + ')\\b', "gi" );
            //If source languace is Cyrillic or CJK
            if ( config.isCJK) {
                re = new RegExp( '(' + glossaryArray.join('|') + ')', "gi" );
            }
        } catch ( e ) {
            return null;
        }
        return re;
    };

    let decorators = decoratorStructure.slice();
    console.log("Add Blacklist Decorator: ", qaCheckGlossary);
    const regex = createGlossaryRegex(qaCheckGlossary);
    _.remove(decorators, (decorator) => decorator.name === 'qaCheckBlacklist');
    decorators.push( generateGlossaryDecorator( regex ) );
    const newDecorator = new CompoundDecorator( decorators );
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};

//Lexiqa
export const activateLexiqa = (editorState, decoratorStructure, lexiqaWarnings, sid, isSource, getUpdatedSegmentInfo) => {

    const generateLexiqaDecorator = (warnings, sid, isSource, decoratorName) => {
        return {
            name: 'lexiqa',
            strategy: (contentBlock, callback, contentState) => {
                _.each(warnings, (warn)=>{
                    if(warn.blockKey === contentBlock.getKey()){
                        const canDecorate = DraftMatecatUtils.canDecorateRange(warn.start, warn.end, contentBlock, contentState, decoratorName);
                        if(canDecorate) callback(warn.start, warn.end);
                        //callback(warn.start, warn.end);
                    }
                });
            },
            component: LexiqaHighlight,
            props: {
                warnings,
                sid,
                isSource,
                getUpdatedSegmentInfo
            }
        };
    };
    /!*console.log("Add Lexiqa Decorator: ", sid, lexiqaWarnings);*!/
    let decorators = decoratorStructure.slice();
    _.remove(decorators, (decorator) => decorator.name === 'lexiqa');
    decorators.push( generateLexiqaDecorator( lexiqaWarnings, sid, isSource, 'lexiqa' ) );
    const newDecorator = new CompoundDecorator( decorators );

    // Remove focus on source to avoid cursor jumping at beginning of target
    if(isSource){
        editorState = EditorState.acceptSelection(editorState, editorState.getSelection().merge({
            hasFocus: false
        }));
    }
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};
*/
