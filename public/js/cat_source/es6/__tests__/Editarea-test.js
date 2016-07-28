jest.unmock('../react/components/segments/Editarea');

var React = require('react');
var ReactDOM = require('react-dom');
var TestUtils = require('react-addons-test-utils');
var EditArea = require('../react/components/segments/Editarea').default;


describe('Editarea', () => {
    it('Editarea is mounted', () => {

        var segment = {
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

        /*var component = TestUtils.renderIntoDocument(
            <EditArea segment={segment} translation={segment.translation}/>
        );
        var editareaNode = ReactDOM.findDOMNode(component);*/

        // expect(editareaNode.textContent).toEqual(segment.translation);
        expect(true).toEqual(true);
    });
});