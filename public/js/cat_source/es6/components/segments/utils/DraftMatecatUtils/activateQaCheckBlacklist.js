import CompoundDecorator from "../CompoundDecorator";
import { EditorState } from 'draft-js';
import * as DraftMatecatConstants from "./editorConstants";
import _ from "lodash";
import QaCheckBlacklistHighlight from "../../GlossaryComponents/QaCheckBlacklistHighlight.component";

const activateQaCheckBlacklist = (editorState, decoratorStructure, qaCheckGlossary) => {

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
    _.remove(decorators, (decorator) => decorator.name === DraftMatecatConstants.QA_BLACKLIST_DECORATOR);
    decorators.push( generateGlossaryDecorator( regex ) );
    const newDecorator = new CompoundDecorator( decorators );
    return {
        editorState: EditorState.set( editorState, {decorator: newDecorator} ),
        decorators: decorators
    }
};

export default activateQaCheckBlacklist;