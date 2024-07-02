import {isMacOS} from './Utils'

export const Shortcuts = {
  shortCutsKeyType: isMacOS() ? 'mac' : 'standard',
  cattol_formatting_characters: {
    label: 'Formatting characters',
    events: {
      nonBreakingSpace: {
        label: 'Non-breaking space',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+shift+space',
          mac: 'option+space',
        },
      },
      wordJoiner: {
        label: 'Word joiner',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+alt+space',
          mac: 'shift+space',
        },
      },
      singleQuoteOpen: {
        label: 'Opening single curly quote',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+bracketLeft',
          mac: 'ctrl+bracketLeft',
        },
      },
      singleQuoteClose: {
        label: 'Closing single curly quote (apostrophe)',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+bracketRight',
          mac: 'ctrl+bracketRight',
        },
      },
      doubleQuoteOpen: {
        label: 'Opening double curly quotes',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+shift+bracketLeft',
          mac: 'ctrl+shift+bracketLeft',
        },
      },
      doubleQuoteClose: {
        label: 'Closing double curly quotes',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+shift+bracketRight',
          mac: 'ctrl+shift+bracketRight',
        },
      },
    },
  },
  cattol: {
    label: 'Edit page operations',
    events: {
      openShortcutsModal: {
        label: 'Open shortcuts window',
        equivalent: 'Open shortcuts window',
        keystrokes: {
          standard: 'alt+h',
          mac: 'ctrl+h',
        },
      },
      translate: {
        label: 'Confirm translation',
        equivalent: 'click on Translated',
        keystrokes: {
          standard: 'ctrl+return',
          mac: 'meta+return',
        },
      },
      translate_nextUntranslated: {
        label: 'Confirm translation and go to Next untranslated segment',
        equivalent: 'click on [T+>>]',
        keystrokes: {
          standard: 'ctrl+shift+return',
          mac: 'meta+shift+return',
        },
      },
      openNext: {
        label: 'Go to next segment',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+down',
          mac: 'meta+down',
        },
      },
      openPrevious: {
        label: 'Go to previous segment',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+up',
          mac: 'meta+up',
        },
      },
      gotoCurrent: {
        label: 'Go to current segment',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+shift+f',
          mac: 'meta+shift+f',
        },
      },
      copySource: {
        label: 'Copy source to target',
        equivalent: 'click on > between source and target',
        keystrokes: {
          standard: 'ctrl+i',
          mac: 'ctrl+i',
        },
      },
      undoInSegment: {
        label: 'Undo in segment',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+z',
          mac: 'meta+z',
        },
      },
      redoInSegment: {
        label: 'Redo in segment',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+y',
          mac: 'meta+shift+z',
        },
      },
      openSearch: {
        label: 'Open search panel',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+f',
          mac: 'meta+f',
        },
      },
      searchInConcordance: {
        label:
          'Perform TM Search search on word(s) selected in the source segment',
        equivalent: '',
        keystrokes: {
          standard: 'alt+k',
          mac: 'meta+k',
        },
      },
      openSettings: {
        label: 'Open Settings panel',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+shift+s',
          mac: 'meta+shift+s',
        },
      },
      openComments: {
        label: 'Open comments in current segment',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+shift+c',
          mac: 'meta+shift+c',
        },
      },
      openIssuesPanel: {
        label: 'Open issues panel',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+shift+a',
          mac: 'meta+shift+a',
        },
      },
      navigateIssues: {
        label: 'Navigate issues panel / Add issue',
        equivalent: {
          standard: 'Ctrl + Alt + Arrows/Enter',
          mac: 'Ctrl + Option + Arrows/Enter',
        },
        keystrokes: {
          standard: 'ctrl+alt+arrows-enter',
          mac: 'ctrl+option+arrows-enter',
        },
      },
      copyContribution1: {
        label: 'Copy first translation match in Target',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+1',
          mac: 'ctrl+1',
        },
      },
      copyContribution2: {
        label: 'Copy second translation match in Target',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+2',
          mac: 'ctrl+2',
        },
      },
      copyContribution3: {
        label: 'Copy third translation match in Target',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+3',
          mac: 'ctrl+3',
        },
      },
      splitSegment: {
        label: 'Split Segment',
        equivalent: '',
        keystrokes: {
          standard: 'ctrl+s',
          mac: 'ctrl+s',
        },
      },
      addNextTag: {
        label: 'Open tags menu',
        equivalent: '',
        keystrokes: {
          standard: 'alt+t',
          mac: 'option+t',
        },
      },
      navigateTabs: {
        label: 'Navigate segment tabs',
        equivalent: '',
        keystrokes: {
          standard: 'alt+s',
          mac: 'ctrl+option+s',
        },
      },
    },
  },
}
