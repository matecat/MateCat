jest.unmock('../react/components/segments/Editarea');
jest.unmock('../react/stores/SegmentStore');

import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import EditArea from '../react/components/segments/Editarea';

var segment;

describe('Editarea', () => {

    beforeEach(() => {
        window.Speech2Text = {};
        window.config = {};
        window.config.target_lang = "EN";
        Speech2Text.enabled = function () {};
        segment = {
            "last_opened_segment":"60310",
            "sid":"60310",
            "segment":"Lorem Ipsum è un testo segnaposto utilizzato nel settore della tipografia e della stampa.",
            "raw_word_count":"10.00",
            "translation":"Lorem Ipsum is simply dummy text of the printing and typesetting industry.",
            "readonly":"false",
            "autopropagated_from":"0",
            "repetitions_in_chunk":"1",
            "has_reference":"false",
        };
    });

    it('Editarea is rendered right', () => {

        var component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        expect(editareaNode.textContent).toBeDefined();
    });

    it('Editarea is rendered right and without microphone', () => {


        var component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        expect(editareaNode.textContent).toEqual(segment.translation);
        expect(editareaNode.className).toContain('editarea');
        expect(editareaNode.className).not.toContain('micActive');
        expect(editareaNode.id).toEqual('segment-' + segment.sid + '-editarea');
        expect(editareaNode.lang).toEqual('en');

        expect(editareaNode.getAttribute('contenteditable')).toBeTruthy();
        expect(editareaNode.getAttribute('spellCheck')).toBeTruthy();
        expect(editareaNode.getAttribute('data-sid')).toEqual("60310");
    });

    it('Editarea is rendered with the microphone', () => {

        Speech2Text.enabled = function () {
            return true;
        };

        var component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        expect(editareaNode.textContent).toEqual(segment.translation);
        expect(editareaNode.className).toContain('editarea');
        expect(editareaNode.className).toContain('micActive');
        expect(editareaNode.id).toEqual('segment-' + segment.sid + '-editarea');
        expect(editareaNode.lang).toEqual('en');
        expect(editareaNode.getAttribute('contenteditable')).toEqual("true");
        expect(editareaNode.getAttribute('spellCheck')).toBeTruthy();
        expect(editareaNode.getAttribute('data-sid')).toEqual("60310");
    });

    it('Editarea is not editable if the segment is readonly', () => {

        segment.readonly = 'true';
        var component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        expect(editareaNode.className).toContain('area');
        expect(editareaNode.getAttribute('contentEditable')).toEqual("false");
    });

    it('Editarea highlight', () => {

        var Costants = require('../react/constants/SegmentConstants');
        var SegmentStore = require('../react/stores/SegmentStore');


        var component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        // spyOn(component, 'hightlightEditarea');
        // component.highlightEditarea(60310);
        SegmentStore.emitChange(Costants.HIGHLIGHT_EDITAREA, "60310");

        // expect(component.hightlightEditarea).toHaveBeenCalled();
        expect(editareaNode.className).toContain('highlighted1');

        //
        // setTimeout(function() {
        //     expect(editareaNode.className).toContain('highlighted2');
        //     expect(editareaNode.className).toContain('highlighted1');
        // }, 300);
        // setTimeout(function() {
        //     expect(editareaNode.className).not.toContain('highlighted2');
        //     expect(editareaNode.className).not.toContain('highlighted1');
        // }, 2000);
    });
});