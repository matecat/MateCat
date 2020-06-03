import findTagWithRegex from "./findTagWithRegex";
import {tagSignatures} from "./tagModel"


/**
 *
 * @param plainContent
 * @returns {*[]} array of all tag occurrences in plainContent
 */
const matchTag = (plainContent) => {

    // Escape line feed or it counts as 1 position that disappear when you create the ContentBlock
    const plainContentLineFeedEscaped = plainContent.replace(/\n/g,'');

    // STEP 1 - Find all opening and save offset
    let tagMap;
    let openTags = [];
    for (let key in tagSignatures) {
        if(!tagSignatures[key].selfClosing && !tagSignatures[key].isClosure){
            tagMap = findTagWithRegex(plainContentLineFeedEscaped, tagSignatures[key]);
            openTags = [...openTags, ...tagMap]
        }
    }

    // STEP 2 - Find all closing and save offset
    let closingTags = [];
    for (let key in tagSignatures) {
        if(tagSignatures[key].isClosure){
            tagMap = findTagWithRegex(plainContentLineFeedEscaped, tagSignatures[key]);
            closingTags = [...closingTags, ...tagMap]
        }
    }

    // STEP 3 - Find all self-closing tag and save offset
    let selfClosingTags = [];
    for (let key in tagSignatures) {
        if(tagSignatures[key].selfClosing){
            tagMap = findTagWithRegex(plainContentLineFeedEscaped, tagSignatures[key]);
            selfClosingTags = [...selfClosingTags, ...tagMap]
        }
    }

    // STEP 4 - Sort arrays by offset
    openTags.sort((a, b) => {return b.offset-a.offset});
    closingTags.sort((a, b) => {return a.offset-b.offset});

    // STEP 5 - Matching non self-closing with each closure
    // Assuming that closure is the same for every tag: '</>'
    closingTags.forEach( closingTag => {
        let i = 0, notFound = true;
        while(i < openTags.length && notFound) {
            if(closingTag.offset > openTags[i].offset && !openTags[i].data.closeTagId ){
                notFound = !notFound;
                const uniqueId = openTags[i].offset + '-' + closingTag.offset;
                openTags[i].data.closeTagId = uniqueId;
                closingTag.data.openTagId = uniqueId;
                // Closing tag has no ID, so take the one available inside open tag
                closingTag.data.id = openTags[i].data.id;
                // Placeholder for closing (cases: <g>, <ex>)
                closingTag.data.placeholder = openTags[i].data.id ;
            }
            i++;
        }
    });

    return [...openTags, ...closingTags, ...selfClosingTags];
};

export default matchTag;
