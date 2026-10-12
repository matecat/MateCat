export default {
  items: [
    {
      id: 0,
      name: 'Standard',
      is_default: true,
      uid: 1,
      id_team: 1,
      segmentation_rule: {
        name: 'General',
        id: 'standard',
      },
      mt: {
        id: 1,
        extra: {},
      },
      tm: [],
      payable_rate_template_id: 0,
      qa_model_template_id: 0,
      get_public_matches: true,
      pretranslate: {
        match_101: {enabled: true, status: 'APPROVED', lock: true},
        match_100: {enabled: false, status: 'TRANSLATED', lock: false},
      },
      created_at: 'Fri, 02 Feb 24 16:48:34 +0100',
      modified_at: 'Fri, 02 Feb 24 16:48:34 +0100',
    },
    {
      id: 3,
      name: 'Testing template',
      id_team: 45,
      is_default: false,
      qa_model_template_id: 6,
      payable_rate_template_id: 2,
      segmentation_rule: {
        name: 'General',
        id: 'standard',
      },
      mt: {
        id: 10,
        extra: {
          deepl_formality: 'prefer_less',
        },
      },
      tm: [
        {
          glos: true,
          is_shared: false,
          key: '74b6c82408a028b6f020',
          name: 'abc',
          owner: true,
          tm: true,
          r: true,
          w: false,
        },
        {
          glos: true,
          is_shared: false,
          key: '21df10c8cce1b31f2d0d',
          name: 'myKey',
          owner: true,
          tm: true,
          r: true,
          w: true,
        },
      ],
      get_public_matches: false,
      pretranslate: {
        match_101: {enabled: true, status: 'APPROVED', lock: true},
        match_100: {enabled: true, status: 'TRANSLATED', lock: false},
      },
    },
  ],
}
