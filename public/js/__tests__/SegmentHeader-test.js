
import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import { mount, shallow } from 'enzyme';


import SegmentHeader from '../cat_source/es6/react/components/segments/SegmentHeader';

var segment;
var expect = require('expect.js');
var assert = chai.assert;
var sinon = require('sinon');

describe('SegmentHeader Component', () => {


    it('SegmentHeader is rendered right', () => {
        var component = TestUtils.renderIntoDocument(
            <SegmentHeader sid="1" autopropagated="false"/>
        );

        var segmentHeader = ReactDOM.findDOMNode(component);
        assert.equal(segmentHeader.id, "segment-1-header");

        ReactDOM.unmountComponentAtNode(ReactDOM.findDOMNode(component).parentNode);

    });

    it('SegmentHeader: Changing percentuage state', () => {

        var component = shallow(
            <SegmentHeader sid="1" autopropagated={false}/>
        );
        expect(component.find('.repetition')).to.have.length(0);

        component.setState({
            percentage: '100%',
            visible: true,
            classname: 'per-green',
            createdBy: 'Francesco Totti'
        });
        expect(component.find('.percentuage')).to.have.length(1);
        expect(component.text()).to.have.equal('100%');
        expect(component.find('.percentuage').hasClass('per-green')).to.equal(true);
        expect(component.find('.percentuage').hasClass('visible')).to.equal(true);
        expect(component.find('.percentuage').props().title).to.equal("Created by Francesco Totti");
    });

    it('SegmentHeader: Set as autopropagated', () => {

        var component = shallow(
            <SegmentHeader sid="1" autopropagated={false}/>
        );
        expect(component.find('.repetition')).to.have.length(0);

        component.setState({
            percentage: '',
            visible: false,
            autopropagated: true,
        });

        expect(component.find('.percentuage')).to.have.length(0);
        expect(component.find('.repetition')).to.have.length(1);
        expect(component.text()).to.have.equal('Autopropagated');
    });

    it('SegmentHeader: Hide Header', () => {


        var component = shallow(
            <SegmentHeader sid="1" autopropagated={true}/>
        );
        expect(component.find('.repetition')).to.have.length(1);

        component.setState({
            autopropagated: false,
            visible: false
        });
        expect(component.find('.repetition')).to.have.length(0);
        expect(component.find('.percentuage')).to.have.length(0);
    });


    it('SegmentHeader: Hide Header with Store event', () => {
        var Costants = require('../cat_source/es6/react/constants/SegmentConstants');
        var SegmentStore = require('../cat_source/es6/react/stores/SegmentStore');

        var component = mount(
            <SegmentHeader sid="1" autopropagated={true}/>
        );
        expect(component.find('.repetition')).to.have.length(1);

        SegmentStore.emitChange(Costants.HIDE_SEGMENT_HEADER, "1");

        expect(component.find('.repetition')).to.have.length(0);
        expect(component.find('.percentuage')).to.have.length(0);
    });


});