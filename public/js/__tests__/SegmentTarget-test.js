
import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import { mount, shallow } from 'enzyme';


import SegmentTarget from '../cat_source/es6/react/components/segments/SegmentTarget';
import EditArea from '../cat_source/es6/react/components/segments/Editarea';
var expect = require('expect.js');
var assert = chai.assert;
var sinon = require('sinon');
var segment;
describe('SegmentTarget component', () => {
    beforeEach(() => {

        segment = {
            "last_opened_segment":"61079",
            "sid":"60984",
            "segment":"INDIETRO",
            "segment_hash":"0a7e4ea10d93b636d9de15132300870c",
            "raw_word_count":"1.00",
            "internal_id":"P147242AB-tu19",
            "translation":"Lorem ipsum dolor sit amet, consectetur adipiscing elit",
            "version":null,
            "original_target_provied":"0",
            "status":"NEW",
            "time_to_edit":"0",
            "xliff_ext_prec_tags":"",
            "xliff_ext_succ_tags":"",
            "warning":"0",
            "suggestion_match":"85",
            "source_chunk_lengths":[],
            "target_chunk_lengths":{
                "len":[0],
                "statuses":["DRAFT"]
            },
            "readonly":"false",
            "autopropagated_from":"0",
            "repetitions_in_chunk":"1",
            "has_reference":"false",
            "parsed_time_to_edit":["00","00","00","00"],
            "notes":null
        }
    });

    it('SegmentTarget is rendered right', () => {
        var decodeTextMock = function (seg, text) {
            return text;
        };
        var speech2textEnable = function() {
            return false;
        };
        var fnMock = function () {
            return true;
        };
        var component = shallow(
            <SegmentTarget
                segment={segment}
                isReviewImproved={false}
                enableTagProjection={false}
                decodeTextFn={decodeTextMock}
                tagModesEnabled={true}
                speech2textEnabledFn={speech2textEnable}
                afterRenderOrUpdate={fnMock}
                beforeRenderOrUpdate={fnMock}
            />
        );

        expect(component.find('.target')).to.have.length(1);
        expect(component.find('.segment_text_area_container')).to.have.length(0);

        expect(component.find('.micSpeech')).to.have.length(0);
        expect(component.find('.tagModeToggle')).to.have.length(1);
        expect(component.find('.textarea-container')).to.have.length(1);
        expect(component.find('.toolbar')).to.have.length(1);
        expect(component.find('.buttons')).to.have.length(1);

        expect(component.find(EditArea)).to.have.length(1);
        expect(component.find(EditArea).prop('translation')).to.equal(segment.translation);
        expect(component.find(EditArea).prop('segment')).to.equal(segment);
    });

    it('SegmentTarget: Speech2Text active', () => {
        var decodeTextMock = function (seg, text) {
            return text;
        };
        var speech2textEnable = function() {
            return true;
        };
        var fnMock = function () {
            return true;
        };
        var component = shallow(
            <SegmentTarget
                segment={segment}
                isReviewImproved={false}
                enableTagProjection={false}
                decodeTextFn={decodeTextMock}
                tagModesEnabled={true}
                speech2textEnabledFn={speech2textEnable}
                afterRenderOrUpdate={fnMock}
                beforeRenderOrUpdate={fnMock}
            />
        );

        expect(component.find('.target')).to.have.length(1);
        expect(component.find('.segment_text_area_container')).to.have.length(0);

        expect(component.find('.micSpeech')).to.have.length(1);
        expect(component.find('.tagModeToggle')).to.have.length(1);
        expect(component.find('.textarea-container')).to.have.length(1);
        expect(component.find('.toolbar')).to.have.length(1);
        expect(component.find('.buttons')).to.have.length(1);

        expect(component.find(EditArea)).to.have.length(1);
        expect(component.find(EditArea).prop('translation')).to.equal(segment.translation);
        expect(component.find(EditArea).prop('segment')).to.equal(segment);
    });

    it('SegmentTarget: Tag Projections active', () => {
        var decodeTextMock = function (seg, text) {
            return text;
        };
        var speech2textEnable = function() {
            return false;
        };
        var fnMock = function () {
            return true;
        };
        var component = shallow(
            <SegmentTarget
                segment={segment}
                isReviewImproved={false}
                enableTagProjection={true}
                decodeTextFn={decodeTextMock}
                tagModesEnabled={true}
                speech2textEnabledFn={speech2textEnable}
                afterRenderOrUpdate={fnMock}
                beforeRenderOrUpdate={fnMock}

            />
        );

        expect(component.find('.target')).to.have.length(1);
        expect(component.find('.segment_text_area_container')).to.have.length(0);

        expect(component.find('.micSpeech')).to.have.length(0);
        expect(component.find('.tagModeToggle')).to.have.length(0);
        expect(component.find('.textarea-container')).to.have.length(1);
        expect(component.find('.toolbar')).to.have.length(1);
        expect(component.find('.buttons')).to.have.length(1);

        expect(component.find(EditArea)).to.have.length(1);
        expect(component.find(EditArea).prop('translation')).to.equal(segment.translation);
        expect(component.find(EditArea).prop('segment')).to.equal(segment);
    });

    it('SegmentTarget: replace Translation', () => {
        var decodeTextMock = function (seg, text) {
            return text;
        };
        var speech2textEnable = function() {
            return false;
        };
        var fnMock = function () {
            return true;
        };
        var component = mount(
            <SegmentTarget
                segment={segment}
                isReviewImproved={false}
                enableTagProjection={false}
                decodeTextFn={decodeTextMock}
                tagModesEnabled={true}
                speech2textEnabledFn={speech2textEnable}
                afterRenderOrUpdate={fnMock}
                beforeRenderOrUpdate={fnMock}
            />
        );

        expect(component.find('.target')).to.have.length(1);
        expect(component.find(EditArea)).to.have.length(1);
        expect(component.find(EditArea).prop('translation')).to.equal(segment.translation);

        component.setState({
            translation: "New translation"
        });

        expect(component.find(EditArea).prop('translation')).to.equal("New translation");

    });
});