import CompoundDecorator from "../CompoundDecorator";
import {CompositeDecorator, EditorState} from 'draft-js';
import * as DraftMatecatConstants from "./editorConstants";
import _ from "lodash";
import QaCheckBlacklistHighlight from "../../GlossaryComponents/QaCheckBlacklistHighlight.component";
import TextUtils from "../../../../utils/textUtils";

const activateQaCheckBlacklist = (qaCheckGlossary) => {

    const generateGlossaryDecorator = (regex) => {
        return {
            name: DraftMatecatConstants.QA_BLACKLIST_DECORATOR,
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
            const escapedMatches = glossaryArray.map((match)=>TextUtils.escapeRegExp(match));
            re = new RegExp( '\\b(' + escapedMatches.join('|') + ')\\b', "gi" );
            //If source languace is Cyrillic or CJK
            if ( config.isCJK) {
                re = new RegExp( '(' + escapedMatches.join('|') + ')', "gi" );
            }
        } catch ( e ) {
            return null;
        }
        return re;
    };

    console.log("Add Blacklist Decorator: ", qaCheckGlossary);
    const regex = createGlossaryRegex(qaCheckGlossary);
    return generateGlossaryDecorator( regex )
};

export default activateQaCheckBlacklist;