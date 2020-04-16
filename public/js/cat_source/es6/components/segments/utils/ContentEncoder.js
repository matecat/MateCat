import {
    EditorState,
    Modifier,
    SelectionState
} from 'draft-js';


/**
 *
 * @param tag
 * @returns {string} decodedTagData - Decoded data inside tag
 */
export const decodeTagInfo = (tag) => {
    let decodedTagData;
    // Todo: take out this structure
    const tagInfoRegex = {
        'ph': {
            regex: /equiv-text="base64:(.+)"/, // Capture base64 text inside
            decodeNeeded: true,
        },
        'g': {
            regex: /(id="\w+")/, // Capture id
            decodeNeeded: false,
        },
    };
    // Todo: add check to be sure that each tag is available and has got his own regex
    if(tag.type in tagInfoRegex) {
        const idMatch = tagInfoRegex[tag.type].regex.exec(tag.data.originalText);
        if(idMatch && idMatch.length > 1) {
            decodedTagData =  tagInfoRegex[tag.type].decodeNeeded ? atob(idMatch[1]) : idMatch[1];
            decodedTagData = unescapeHTML(decodedTagData);
        }else{
            decodedTagData = '</>'
        }
    }else{
        decodedTagData = '</>'
    }
    return decodedTagData;
};

/**
 *
 * @param escapedHTML
 * @returns {string}
 */
export const unescapeHTML = (escapedHTML) => {
    return escapedHTML.replace(/&lt;/g,'<').replace(/&gt;/g,'>')
};

/**
 *
 * @param tagRange
 * @param editorState
 * @returns {ContentState}  contentState - A ContentState with each tag as an entity
 */
export const createNewEntitiesFromMap = (tagRange, editorState) => {
    let contentState = editorState.getCurrentContent();
    // Got only one block: the segment
    const contentBlock = contentState.getFirstBlock();
    const blockKey = contentBlock.getKey();

    tagRange.forEach( tag =>{
        // Clone tag
        let tagEntity = {...tag};
        // Select slice of text containing entity
        const selectionState = new SelectionState({
            anchorKey: blockKey,
            anchorOffset: tag.offset,
            focusKey: blockKey,
            focusOffset: tag.offset + tag.length
        });
        // Decode tag data and place them cleaned inside tag object
        tagEntity.data.placeHolder = decodeTagInfo(tagEntity);
        // Create entity
        const {type, mutability, data} = tagEntity;
        const contentStateWithEntity = contentState.createEntity(type, mutability, data);
        const entityKey = contentStateWithEntity.getLastCreatedEntityKey();
        // Apply entity on the previous selection
        contentState = Modifier.applyEntity(
            contentState,
            selectionState,
            entityKey
        );
    });
    return contentState
};

/**
 *
 * @param editorState
 * @param entityRange
 * @returns {ContentState} - A ContentState in which each tag that is not self-closable, is linked to another tag
 */
export const linkEntities  = (editorState, entityRange) => {
    let contentState = editorState.getCurrentContent();
    const entityToLink = entityRange.filter( entity => {
        return entity.data.closureOffset && entity.data.closureOffset !== -1
    });
    let contentBlock, openingEntityKey, closingEntityKey;
    entityToLink.forEach( tag => {
        contentBlock = contentState.getFirstBlock();
        openingEntityKey = contentBlock.getEntityAt(tag.offset);
        closingEntityKey = contentBlock.getEntityAt(tag.data.closureOffset);
        // Todo: clean various offsets placed in data:{} inside entities
        contentState = contentState.mergeEntityData( closingEntityKey, {openTagId: openingEntityKey} );
        contentState = contentState.mergeEntityData( openingEntityKey, {closeTagId: closingEntityKey} );
    });
    return contentState;
};

/**
 *
 * @param editorState
 * @returns {ContentState} contentState - A a new ContentState in which entities are displayed as placeholder
 */
export const beautifyEntities  = (editorState) => {
    const inlineStyle = editorState.getCurrentInlineStyle();
    const entities = getEntities(editorState); //start - end
    let entityKeys =  entities.map( entity => entity.entityKey);
    let contentState = editorState.getCurrentContent();
    let contentBlock, blockKey;
    let editorStateClone = editorState;
    // Loop through keys and update entities text
    entityKeys.forEach( key => {
        // Update entities cause previous cycle updated offsets
        let entitiesInEditor = getEntities(editorStateClone);
        // Filter only looped tag
        let tag = entitiesInEditor.filter( tag => tag.entityKey === key)[0];
        contentBlock = contentState.getFirstBlock();
        blockKey = contentBlock.getKey();
        // Get DraftEntity to retrieve data
        // TODO: provare con tag.entity.getData()
        let tagInstance = contentState.getEntity(tag.entityKey);
        let {placeHolder} = tagInstance.getData();
        // Use selection based on temporary contentState
        let selectionState = new SelectionState({
            anchorKey: blockKey,
            anchorOffset: tag.start,
            focusKey: blockKey,
            focusOffset: tag.end
        });
        // Replace text of entity
        contentState = Modifier.replaceText(
            contentState,
            selectionState,
            placeHolder,
            inlineStyle,
            tag.entityKey
        );
        // Update contentState
        editorStateClone = EditorState.set(editorStateClone, {currentContent: contentState});
    });
    return contentState;
};

/**
 *
 * @param editorState
 * @param entityType
 * @returns {[]} An array of entities with each entity position
 */
export const getEntities = (editorState, entityType = null) => {
    const content = editorState.getCurrentContent();
    const entities = [];
    content.getBlocksAsArray().forEach((block) => {
        let selectedEntity = null;
        block.findEntityRanges(
            (character) => {
                if (character.getEntity() !== null) {
                    const entity = content.getEntity(character.getEntity());
                    if (!entityType || (entityType && entity.getType() === entityType)) {
                        selectedEntity = {
                            entityKey: character.getEntity(),
                            blockKey: block.getKey(),
                            entity: content.getEntity(character.getEntity()),
                        };
                        return true;
                    }
                }
                return false;
            },
            (start, end) => {
                entities.push({...selectedEntity, start, end});
            });
    });
    return entities;
};


export const generateBlocksForRaw = (originalContent, entitySet) => {
    let blocks = [];
    let entityRanges = [];
    entitySet.forEach( ({offset, length, key}) => {
        entityRanges.push({offset, length, key})
    });

    blocks.push({
        text: originalContent,
        type: 'unstyled',
        entityRanges: entityRanges
    });

    return blocks;
};

export const generateEntityMapForRaw = (originalContent, entitySet) => {
    let entityMap = {};
    console.log('Set: ', entitySet)
    entitySet.forEach( ({key, type, mutability, data}) => {
        entityMap[key] = {
                type: type,
                mutability: mutability,
                data: data
            };
        console.log('Added: ', entityMap[key])
    });
    return entityMap;
};

export const replaceEntityText = (entity, editorState) => {
    const contentState = editorState.getCurrentContent();
    const selectionState = editorState.getSelection().merge({
        anchorOffset: entity.offset,
        focusOffset: entity.offset + entity.length
    });
    console.log('Selezione: ',selectionState);
    const replacedText = Modifier.replaceText(contentState, selectionState, '&lt;ph ');
    console.log(replacedText);

    const newEditorState = EditorState.push(
        editorState,
        replacedText,
        'insert-characters'
    );

    return newEditorState;
};

/**
 *
 * @param editorState
 * @returns {*|EditorState} editorStateModified - An EditorState with all known tags treated as entities
 */
export const encodeContent = (editorState) => {

    const residualContentToParse = editorState.getCurrentContent().getPlainText();
    // Match open and closing tags
    const tagRange = matchTag(residualContentToParse);
    // Create entities
    let newContent = createNewEntitiesFromMap(tagRange, editorState);
    // Apply entities to EditorState
    let editorStateModified = EditorState.push(editorState, newContent, 'apply-entity');
    // Link each openTag with its closure
    newContent = linkEntities(editorStateModified, tagRange);
    editorStateModified = EditorState.push(editorState, newContent, 'change-block-data');
    // Replace each tag text with a placeholder
    newContent = beautifyEntities(editorStateModified);
    editorStateModified = EditorState.push(editorState, newContent, 'change-block-data');
    return editorStateModified;
};

/**
 *
 * @param plainContent
 * @returns {*[]} array of all tag occurrences in plainContent
 */
export const matchTag = (plainContent) => {

    // Todo: take out as data structure
    const tagRegex = {
        'ph': {
            type: 'ph',
            openRegex: /&lt;ph/g,
            openLength: 6,
            closeRegex: true,
            selfClosing: false,
            isClosure: false
        },
        'g': {
            type: 'g',
            openRegex: /&lt;g/g,
            openLength: 5,
            closeRegex: true,
            selfClosing: false,
            isClosure: false
        },
        'cl': {
            type: 'cl',
            openRegex: /&lt;\/&gt;/g,
            openLength: 9,
            closeRegex: null,
            selfClosing: true,
            isClosure: true
        },
    };

    //findWithRegexV4(plainContent, tagRegex['ph']);

    // STEP 1 - Find all opening and save offset
    let tagMap;
    let openTags = [];
    for (let key in tagRegex) {
        if(!tagRegex[key].selfClosing){
            tagMap = findWithRegexV4(plainContent, tagRegex[key]);
            openTags = [...openTags, ...tagMap]
        }
    }
    console.log('Openings: ', openTags);
    // STEP 2 - Find all closing and save offset
    let closingTags = [];
    for (let key in tagRegex) {
        if(tagRegex[key].isClosure){
            tagMap = findWithRegexV4(plainContent, tagRegex[key]);
            closingTags = [...closingTags, ...tagMap]
        }
    }
    console.log('Closures: ', closingTags);
    // STEP 3 - Sort arrays by offset
    openTags.sort((a, b) => {return b.offset-a.offset});
    closingTags.sort((a, b) => {return a.offset-b.offset});
    // STEP 4 - Matching
    closingTags.forEach( closingTag => {
        let i = 0, notFound = true;
        while(i < openTags.length -1 && notFound) {
            if(closingTag.offset > openTags[i].offset && openTags[i].data.closureOffset === -1){
                notFound = !notFound;
                openTags[i].data.closureOffset = closingTag.offset;
                closingTag.data.openingOffset = openTags[i].offset;
            }
            i++;
        }
    });
    return [...openTags, ...closingTags];
};


/**
 *
 * @param text
 * @param tagSignature
 * @returns {[]} tagRange - array with all occurrences of tagSignature in the input text
 */
export const findWithRegexV4 = (text, tagSignature) => {
    let matchArr;
    let entity = {
        offset: -1,
        length: null,
        type: null
    };
    const {type, openRegex, openLength, closeRegex} = tagSignature;
    const tagRange = [];

    // Todo: remove loop safelock after test
    let lockdown = 0; // Never bet on a while loop
    console.log('Searching for: ', type);
    while((matchArr = openRegex.exec(text)) !== null && lockdown < 100){
        lockdown = lockdown +1;
        entity.offset = matchArr.index;
        if(!closeRegex) {
            entity.length = openLength;
            entity.data = {'openingOffset': -1, 'openTagId': null};
        }else {
            // Search for tag closure (identical for each tag) start on opening offset
            let slicedText = text.slice(entity.offset, text.length);
            matchArr = /&gt;/.exec(slicedText);
            entity.length = matchArr.index + 5; //Length of previous regex, todo: make this number calculated
            let originalText = text.slice(entity.offset, entity.offset + entity.length);
            entity.data = {'originalText': originalText, 'closureOffset': -1, 'closeTagId': null};
        }
        entity.type = type;
        entity.mutability = 'IMMUTABLE';
        tagRange.push({...entity});
    }
    console.log('Tag range: ', tagRange);
    return tagRange;
};
