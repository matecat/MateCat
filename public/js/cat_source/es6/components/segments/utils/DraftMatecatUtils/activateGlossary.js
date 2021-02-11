import GlossaryComponent from "../../GlossaryComponents/GlossaryHighlight.component";
import TextUtils from "../../../../utils/textUtils.js";
import {CompositeDecorator, EditorState} from 'draft-js';
import * as DraftMatecatConstants from "./editorConstants";
import _ from "lodash";
import canDecorateRange from "./canDecorateRange";

export const activateGlossary = (editorState, glossary, text, sid, segmentAction) => {

    const generateGlossaryDecorator = (regex, sid) => {
        return {
            name: DraftMatecatConstants.GLOSSARY_DECORATOR,
            strategy: (contentBlock, callback, contentState) => {
                if ( regex !== '') {
                    findWithRegex(regex,contentState, contentBlock, callback, DraftMatecatConstants.GLOSSARY_DECORATOR);
                }
            },
            component: GlossaryComponent,
            props: {
                sid: sid,
                onClickAction: segmentAction
            }
        };
    };

    const findWithRegex = (regex, contentState, contentBlock, callback, decoratorName) => {
        const text = contentBlock.getText();
        let matchArr, start, end;
        while ((matchArr = regex.exec(text)) !== null) {
            start = matchArr.index;
            end = start + matchArr[0].length;
            const canDecorate = canDecorateRange(start, end, contentBlock, contentState, decoratorName);
            if(canDecorate) callback(start, end);
            //callback(start, end);
        }
    };

    const createGlossaryRegex = (glossaryObj, text) => {
        let re;
        try {
            const matches = _.map(glossaryObj, ( elem ) => (elem[0].raw_segment) ? elem[0].raw_segment: elem[0].segment);
            const escapedMatches = matches.map((match)=>TextUtils.escapeRegExp(match));
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

    const regex = createGlossaryRegex(glossary, text);
    return generateGlossaryDecorator( regex, sid )
};

export default activateGlossary;