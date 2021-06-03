import {render, screen} from '@testing-library/react'
import React from 'react'
import Immutable from 'immutable'

import {QualitySummaryTableOldRevise} from './QualitySummaryTableOldRevise'

test('render properly', () => {
  render(
    <QualitySummaryTableOldRevise
      qualitySummary={Immutable.fromJS({
        revision_number: 1,
        feedback: null,
        model_version: null,
        equivalent_class: 80,
        is_pass: 0,
        quality_overall: 'excellent',
        errors_count: 0,
        revise_issues: {
          err_typing: {
            allowed: 8.8,
            found: 0,
            founds: {
              minor: '0',
              major: '0',
            },
            vote: 'Excellent',
          },
          err_translation: {
            allowed: 8.8,
            found: 0,
            founds: {
              minor: '0',
              major: '0',
            },
            vote: 'Excellent',
          },
          err_terminology: {
            allowed: 13.1,
            found: 0,
            founds: {
              minor: '0',
              major: '0',
            },
            vote: 'Excellent',
          },
          err_language: {
            allowed: 13.1,
            found: 0,
            founds: {
              minor: '0',
              major: '0',
            },
            vote: 'Excellent',
          },
          err_style: {
            allowed: 21.9,
            found: 0,
            founds: {
              minor: '0',
              major: '0',
            },
            vote: 'Excellent',
          },
        },
        score: 0,
        categories: [
          {
            label: 'Typing',
            id: 'err_typing',
            severities: [
              {
                label: 'minor',
                penalty: 0.03,
              },
              {
                label: 'major',
                penalty: 1,
              },
            ],
            subcategories: [],
            options: [],
          },
          {
            label: 'Translation',
            id: 'err_translation',
            severities: [
              {
                label: 'minor',
                penalty: 0.03,
              },
              {
                label: 'major',
                penalty: 1,
              },
            ],
            subcategories: [],
            options: [],
          },
          {
            label: 'Terminology',
            id: 'err_terminology',
            severities: [
              {
                label: 'minor',
                penalty: 0.03,
              },
              {
                label: 'major',
                penalty: 1,
              },
            ],
            subcategories: [],
            options: [],
          },
          {
            label: 'Language Quality',
            id: 'err_language',
            severities: [
              {
                label: 'minor',
                penalty: 0.03,
              },
              {
                label: 'major',
                penalty: 1,
              },
            ],
            subcategories: [],
            options: [],
          },
          {
            label: 'Style',
            id: 'err_style',
            severities: [
              {
                label: 'minor',
                penalty: 0.03,
              },
              {
                label: 'major',
                penalty: 1,
              },
            ],
            subcategories: [],
            options: [],
          },
        ],
        total_issues_weight: 0,
        total_reviewed_words_count: 0,
        passfail: '',
        total_time_to_edit: 0,
      })}
    />,
  )

  expect(screen.getByText('Issues')).toBeVisible()

  expect(screen.getByText('minor')).toBeVisible()
  expect(
    screen.getByText(
      (content, element) =>
        content != '' && element.textContent == 'Weight: 0.03',
    ),
  ).toBeVisible()

  expect(screen.getByText('major')).toBeVisible()
  expect(
    screen.getByText(
      (content, element) => content != '' && element.textContent == 'Weight: 1',
    ),
  ).toBeVisible()

  expect(screen.getByText('Total Weight')).toBeVisible()
  expect(screen.getByText('Tolerated Issues')).toBeVisible()

  expect(screen.getByText('Total Score')).toBeVisible()
})
