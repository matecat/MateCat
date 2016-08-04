
import React from 'react';
import ReactDOM from 'react-dom';
import TestUtils from 'react-addons-test-utils';
import { mount, shallow } from 'enzyme';


import SegmentFooter from '../cat_source/es6/react/components/segments/SegmentFooter';

var expect = require('expect.js');
var assert = chai.assert;
var sinon = require('sinon');

describe('SegmentFooter Component', () => {


    it('SegmentFooter is rendered right', () => {
        var component = shallow(
            <SegmentFooter sid="123"/>
        );

        expect(component.find('.footer')).to.have.length(1);


    });
});