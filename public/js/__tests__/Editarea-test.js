

import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import EditArea from '../cat_source/es6/react/components/segments/Editarea';

var segment;
var expect = require('expect.js');
var assert = chai.assert;
var sinon = require('sinon');
import { mount, shallow } from 'enzyme';
var component;

describe('Editarea Component', () => {

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

        component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        expect(editareaNode.textContent).to.not.be.undefined;

        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);
    });

    it('Editarea is rendered right and without microphone', () => {


        component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        assert.equal(editareaNode.textContent, segment.translation, 'Contains the traduction');
        assert.include(editareaNode.className, 'editarea', 'The class "editarea" has been set');
        assert.notInclude(editareaNode.className, 'micActive', 'The class "micActive" has not been set');
        assert.equal(editareaNode.id, 'segment-' + segment.sid + '-editarea', 'The id parameter is correctly set');
        assert.equal(editareaNode.lang, 'en', 'The parameter lang is correctly set');
        expect(editareaNode.getAttribute('contenteditable')).to.be.true;
        expect(editareaNode.getAttribute('spellCheck')).to.be.true;
        assert.equal(editareaNode.getAttribute('data-sid'),"60310" , 'The data-sid parameter is correctly set');

        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);
    });

    it('Editarea is rendered with the microphone', () => {

        Speech2Text.enabled = function () {
            return true;
        };

        component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        assert.equal(editareaNode.textContent, segment.translation, 'Contains the traduction');
        assert.include(editareaNode.className, 'editarea', 'The class "editarea" has been set');
        assert.include(editareaNode.className, 'micActive', 'The class "micActive" has not been set');
        assert.equal(editareaNode.id, 'segment-' + segment.sid + '-editarea', 'The id parameter is correctly set');
        assert.equal(editareaNode.lang, 'en', 'The parameter lang is correctly set');
        expect(editareaNode.getAttribute('contenteditable')).to.equal('true');
        expect(editareaNode.getAttribute('spellCheck')).to.equal('true');
        assert.equal(editareaNode.getAttribute('data-sid'),"60310" , 'The data-sid parameter is correctly set');

        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);
    });

    it('Editarea is not editable if the segment is readonly', () => {

        segment.readonly = 'true';
        component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        assert.include(editareaNode.className, 'area', 'The class "area" has not been set');

        expect(editareaNode.getAttribute('contenteditable')).to.equal('false');

        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);
    });

    it('Editarea highlightEditArea is called', () => {

        var Costants = require('../cat_source/es6/react/constants/SegmentConstants');
        var SegmentStore = require('../cat_source/es6/react/stores/SegmentStore');

        var spy = sinon.spy(EditArea.prototype, 'hightlightEditarea');

        component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );

        SegmentStore.emitChange(Costants.HIGHLIGHT_EDITAREA, "60310");

        expect(EditArea.prototype.hightlightEditarea).to.have.property('callCount', 1);
        expect(spy.called).to.equal(true);

        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);

    });

    it('Editarea highlightEditArea is called and set the class', () => {


        var Costants = require('../cat_source/es6/react/constants/SegmentConstants');
        var SegmentStore = require('../cat_source/es6/react/stores/SegmentStore');

        component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);

        SegmentStore.emitChange(Costants.HIGHLIGHT_EDITAREA, "60310");
        assert.include(editareaNode.className, 'highlighted1');
        // setTimeout(function() {
        //     assert.include(editareaNode.className, 'highlighted2');
        //     assert.include(editareaNode.className, 'highlighted1');
        // }, 300);
        // setTimeout(function() {
        //     assert.notInclude(editareaNode.className, 'highlighted1');
        //     assert.notInclude(editareaNode.className, 'highlighted2');
        // }, 2000);

        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);
    });

    it('Editarea: Translation change', () => {

        component = mount(
            <EditArea segment={segment} translation={segment.translation}/>
        );

        expect(component.text()).to.equal(segment.translation);

        component.setProps({
            translation: "New Translation"
        });

        expect(component.text()).to.equal("New Translation");
    });

});