// Segment fixtures of the editor acceptance suite (from the editor spike), also part of the round-trip corpus.

const ph = (text) =>
  `<ph id="mtc_1" ctype="x-html" equiv-text="base64:${btoa(text)}"/>`
const phc = (id, ctype, text, extra = '') =>
  `<ph id="${id}" ctype="${ctype}" equiv-text="base64:${btoa(text)}"${extra}/>`

export const SEGMENTS = {
  // <ph> variants: the stored markup differs from what the chip shows (decoded equiv-text). Covers x-html,
  // placeholders (%s, {{count}}), a <ph> whose label looks like markup (<b>), XLIFF 2 <pc> pairs carried in <ph>
  // without dataRef (paired by position) and with dataRef (paired by base id source1_1 / source1_2),
  // a nested pair, <g> with equiv-text (compressible), a word joiner, and HTML entities in the text.
  ph: {
    source:
      'Hello' +
      phc('mtc_1', 'x-html', '<br/>') +
      'you have ' +
      phc('mtc_2', 'x-percent-s', '%s') +
      ' new messages from ' +
      phc('mtc_3', 'x-original_pc_open', '<b>') +
      'Anna ' +
      phc('mtc_4', 'x-original_pc_open', '<i>') +
      'Rossi' +
      phc('mtc_5', 'x-original_pc_close', '</i>') +
      phc('mtc_6', 'x-original_pc_close', '</b>') +
      ' &amp; ' +
      phc(
        'source1_1',
        'x-pc_open_data_ref',
        '<a href="#">',
        ' x-orig="base64:PGE+"',
      ) +
      'Bob' +
      phc(
        'source1_2',
        'x-pc_close_data_ref',
        '</a>',
        ' x-orig="base64:PC9hPg=="',
      ) +
      ' (' +
      phc('mtc_7', 'x-mustache', '{{count}}') +
      ' &lt; 5) <g id="9" equiv-text="base64:PHNwYW4+">ok</g>⁠!',
    target:
      'Ciao' +
      phc('mtc_1', 'x-html', '<br/>') +
      'hai ' +
      phc('mtc_2', 'x-percent-s', '%s') +
      ' nuovi messaggi da ' +
      phc('mtc_3', 'x-original_pc_open', '<b>') +
      'Anna ' +
      phc('mtc_4', 'x-original_pc_open', '<i>') +
      'Rossi' +
      phc('mtc_5', 'x-original_pc_close', '</i>') +
      phc('mtc_6', 'x-original_pc_close', '</b>') +
      ' &amp; ' +
      phc(
        'source1_1',
        'x-pc_open_data_ref',
        '<a href="#">',
        ' x-orig="base64:PGE+"',
      ) +
      'Bob' +
      phc(
        'source1_2',
        'x-pc_close_data_ref',
        '</a>',
        ' x-orig="base64:PC9hPg=="',
      ) +
      ' (' +
      phc('mtc_7', 'x-mustache', '{{count}}') +
      ' &lt; 5) <g id="9" equiv-text="base64:PHNwYW4+">ok</g>⁠!',
    lang: {source: 'en-US', target: 'it-IT'},
    dir: 'ltr',
  },
  // Back-to-back tags at line start and end, paired g, ph, nbsp, tab, line feed.
  basic: {
    source:
      '<g id="1"><x id="2"/>Click <g id="3">Save</g> to keep##$_A0$##your changes.<x id="4"/></g>##$_0A$##Second line' +
      ph('{name}') +
      ' here.',
    target:
      '<g id="1"><x id="2"/>Fai clic su <g id="3">Salva</g> per mantenere##$_A0$##le modifiche.<x id="4"/></g>##$_0A$##Seconda riga' +
      ph('{name}') +
      ' qui.',
    lang: {source: 'en-US', target: 'it-IT'},
    dir: 'ltr',
  },
  // RTL target with tags.
  rtl: {
    source: 'Press <g id="1">OK</g> to continue<x id="2"/>',
    target: 'اضغط على <g id="1">موافق</g> للمتابعة<x id="2"/>',
    lang: {source: 'en-US', target: 'ar-SA'},
    dir: 'rtl',
  },
  // CJK target, used for IME tests.
  cjk: {
    source: 'Open the <g id="1">settings</g> page',
    target: '<g id="1">설정</g> 페이지를 엽니다',
    lang: {source: 'en-US', target: 'ko-KR'},
    dir: 'ltr',
  },
  // Special characters the editor shows as display-only marks: em space, line separator, zero-width space.
  special: {
    source:
      'An em\u2003space, a line\u2028separator and a zero\u200bwidth space',
    target:
      'Uno spazio\u2003em, un separatore\u2028di riga e uno spazio\u200bzero',
    lang: {source: 'en-US', target: 'it-IT'},
    dir: 'ltr',
  },
  // A ph whose text is longer than a chip shows (Tag.scss trims it at 320px), next to a short one.
  longTag: {
    source:
      'Open ' +
      phc(
        'mtc_1',
        'x-html',
        '<a href="https://www.matecat.com/a/very/long/path/that/never/fits/in/a/chip" class="link">',
      ) +
      'the link' +
      phc('mtc_2', 'x-html', '<br/>'),
    target:
      'Apri ' +
      phc(
        'mtc_1',
        'x-html',
        '<a href="https://www.matecat.com/a/very/long/path/that/never/fits/in/a/chip" class="link">',
      ) +
      'il link' +
      phc('mtc_2', 'x-html', '<br/>'),
    lang: {source: 'en-US', target: 'it-IT'},
    dir: 'ltr',
  },
  // Deliberate misspellings for the manual spellcheck check.
  spell: {
    source: 'The quick brown fox jumps over the lazy dog',
    target:
      'The quikc brwon fox jumsp over the lazzy dog <x id="1"/> and recieve teh mail',
    lang: {source: 'en-US', target: 'en-US'},
    dir: 'ltr',
  },
}

export const TM_MATCH =
  '<g id="1"><x id="2"/>Clicca <g id="3">Salva</g> per conservare##$_A0$##le modifiche.<x id="4"/></g>##$_0A$##Seconda riga' +
  ph('{name}') +
  ' qui.'
