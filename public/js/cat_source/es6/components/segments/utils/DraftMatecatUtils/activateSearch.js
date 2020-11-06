import SearchHighlight from "../../SearchHighLight/SearchHighLight.component";
import CompoundDecorator from "../CompoundDecorator";
import {CompositeDecorator, EditorState} from 'draft-js';
import * as DraftMatecatConstants from "./editorConstants";
import _ from "lodash";

const activateSearch = (text, params, occurrencesInSegment, currentIndex, tagRange) => {

    const generateSearchDecorator = (highlightTerm, occurrences, params, currentIndex, tagRange) => {
        let regex = SearchUtils.getSearchRegExp(highlightTerm, params.ingnoreCase, params.exactMatch);
        return {
            name: DraftMatecatConstants.SEARCH_DECORATOR,
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
            //!isTag(start, tagRange) && callback(start, end)
            handleTagInside(start, end, contentBlock, callback)
            index++;
        }
    };

    const isTag = (start, tagRange) => {
        let indexToAdd = 0;
        //Note: The list of tags contains the indexes calculated with the ph tags in the text, while the list of occurrences does not.
        let tag = tagRange.find((item)=>{
            let isTag = start + indexToAdd >= item.offset && start + indexToAdd <= item.offset + item.data.placeholder.length;
            indexToAdd += (item.length - item.data.placeholder.length);
            return isTag;
        });
        return !!tag;
    }

    const hasEntity = (charPosition, contentBlock) => {
        return contentBlock.getEntityAt(charPosition);
    }

    const handleTagInside = (start, end, contentBlock, callback ) => {
        let cursor = start;
        while(cursor < end){

            // start
            while(hasEntity(cursor, contentBlock) && cursor < end){
                cursor++
            }
            let tempStart = cursor;
            // end
            while(!hasEntity(cursor, contentBlock) && cursor < end){
                cursor++
            }
            // no entity between, end loop
            if(cursor === tempStart){
                cursor = end
            }
            let tempEnd = cursor
            callback(tempStart, tempEnd)
        }
    }

    let search = text;
    let occurrencesClone = _.cloneDeep(occurrencesInSegment);
    return generateSearchDecorator( search, occurrencesClone, params, currentIndex, tagRange);
};

export default activateSearch;