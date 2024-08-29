### WARNING - Removed PHP 5.6 compatibility. Language support 7.4+

ASANA https://app.asana.com/0/1134617950425092/1207299263774123

## Xliff rules endpoints documentation

### Schema

This endpoint returns the JSON schema used to validate QAModels:

```
GET /api/v3/xliff-config-template/schema
```

As you may notice the schema uses the Swagger naming conventions.

### Create

Use this endpoint to create a template:

```
POST /api/v3/xliff-config-template/
```

Payload example:

```json
{
  "name": "test",
  "rules": {
    "xliff12": [
      {
        "analysis": "new",
        "states": [
          "new",
          "translated",
          "needs-translation"
        ]
      },
      {
        "analysis": "pre-translated",
        "editor": "translated",
        "states": [
          "needs-review-adaptation",
          "needs-review-l10n"
        ],
        "match_category": "50_74"
      },
      {
        "analysis": "pre-translated",
        "editor": "approved",
        "states": [
          "x-ciao",
          "signed-off",
          "final"
        ]
      }
    ],
    "xliff20": [
      {
        "analysis": "new",
        "states": [
          "final",
          "x-pippo"
        ]
      }
    ]
  }
}
```

Response example:

```json
{
  "id": 1,
  "uid": 1,
  "name": "test",
  "rules": {
    "xliff12": [
      {
        "states": [
          "new",
          "translated",
          "needs-translation"
        ],
        "analysis": "new"
      },
      {
        "states": [
          "needs-review-adaptation",
          "needs-review-l10n"
        ],
        "analysis": "pre-translated",
        "editor": "translated",
        "match_category": "50_74"
      },
      {
        "states": [
          "signed-off",
          "final"
        ],
        "analysis": "pre-translated",
        "editor": "approved",
        "match_category": "ice"
      }
    ],
    "xliff20": [
      {
        "states": [
          "final"
        ],
        "analysis": "new"
      }
    ]
  },
  "createdAt": "2024-07-29T17:18:05+02:00",
  "modifiedAt": "2024-08-08T15:44:45+02:00"
}
```

### Modify

Use this endpoint to update an existing template:

```
PUT /api/v3/xliff-config-template/[:id]
```

Payload example:

```json
{
    "name": "test",
    "rules": {
        "xliff12": [
            {
                "analysis": "new",
                "states": [
                    "new",
                    "translated",
                    "needs-translation"
                ]
            }
        ],
        "xliff20": [
            {
                "analysis": "new",
                "states": [
                    "final",
                    "x-pippo"
                ]
            }
        ]
    }
}
```

Response example:

```json
{
    "id": 1,
    "uid": 1,
    "name": "test",
    "rules": {
        "xliff12": [
            {
                "states": [
                    "new",
                    "translated",
                    "needs-translation"
                ],
                "analysis": "new"
            }
        ],
        "xliff20": [
            {
                "states": [
                    "final"
                ],
                "analysis": "new"
            }
        ]
    },
    "createdAt": "2024-07-29T17:18:05+02:00",
    "modifiedAt": "2024-08-08T15:47:09+02:00"
}
```

### Delete

Use this endpoint to delete an existing template:

```
DELETE /api/v3/xliff-config-template/[:id]
```

If no errors are thrown the endpoint will display the id of the new template:

```json
{
    "id": 1
}
```

### Fetch all

Use this endpoint to fetch all templates:

```
GET /api/v3/xliff-config-template/
```

```json
{
    "current_page": 1,
    "per_page": 20,
    "last_page": 1,
    "total_count": 3,
    "prev": null,
    "next": null,
    "items": [
        {
            "id": 1,
            "uid": 1,
            "name": "test",
            "rules": {
                "xliff12": [
                    {
                        "states": [
                            "new"
                        ],
                        "analysis": "new",
                        "editor": "ignore-target-content"
                    }
                ],
                "xliff20": [
                    {
                        "states": [
                            "final"
                        ],
                        "analysis": "new",
                        "editor": "ignore-target-content"
                    }
                ]
            },
            "createdAt": "2024-05-31T16:49:41+02:00",
            "modifiedAt": "2024-05-31T17:16:56+02:00"
        },
        {
            "id": 2,
            "uid": 1,
            "name": "other test",
            "rules": {
                "xliff12": [
                    {
                        "states": [
                            "new"
                        ],
                        "analysis": "new",
                        "editor": "ignore-target-content"
                    }
                ],
                "xliff20": [
                    {
                        "states": [
                            "final"
                        ],
                        "analysis": "new",
                        "editor": "ignore-target-content"
                    }
                ]
            },
            "createdAt": "2024-05-31T16:57:59+02:00",
            "modifiedAt": "2024-05-31T17:09:03+02:00"
        }
    ]
}
```

### Fetch single template

Use this endpoint to fetch a saved template:

```
GET /api/v3/xliff-config-template/[:id]
```

If the id does not belong to an existing model a 404 code will be return.

Response example:

```json
{
    "id": 1,
    "uid": 1,
    "name": "test",
    "rules": {
        "xliff12": [
            {
                "states": [
                    "new"
                ],
                "analysis": "new",
                "editor": "ignore-target-content"
            }
        ],
        "xliff20": [
            {
                "states": [
                    "final"
                ],
                "analysis": "new",
                "editor": "ignore-target-content"
            }
        ]
    },
    "createdAt": "2024-05-31T16:49:41+02:00",
    "modifiedAt": "2024-05-31T17:16:53+02:00",
    "deletedAt": null
}
```

## Filters params endpoints documentation

### Schema

This endpoint returns the JSON schema used to validate QAModels:

```
GET /api/v3/filters-config-template/schema
```

As you may notice the schema uses the Swagger naming conventions.

### Create

Use this endpoint to create a template:

```
POST /api/v3/filters-config-template/
```

Payload example:

```json
{
  "name": "test",
  "json": {
       "extract_arrays": true,
       "translate_keys": ["pappa"]
  	},
    "yaml": {
        "translate_keys": ["saas", "ddddd", "sss"]
    }
}
```

Response example:

```json
{
  "id": 1,
  "uid": 1,
  "name": "test",
  "xml": [],
  "yaml": {
    "translate_keys": [
      "saas",
      "ddddd",
      "sss"
    ]
  },
  "json": {
    "extract_arrays": true,
    "translate_keys": [
      "pappa"
    ]
  },
  "ms_word": [],
  "ms_excel": [],
  "ms_powerpoint": [],
  "createdAt": "2024-08-12T17:57:56+02:00",
  "modifiedAt": null
}
```

### Modify

Use this endpoint to update an existing template:

```
PUT /api/v3/filters-config-template/[:id]
```

Payload example:

```json
{
  "name": "filters-template-modificato",
  "xml": {
    "preserve_whitespace": true,
    "translate_elements": [
      "ciao",
      "casa2"
    ],
    "translate_attributes": []
  },
  "json": {
    "extract_arrays": true,
    "translate_keys": [
      "pappa"
    ]
  },
  "yaml": {
    "translate_keys": [
      "saas",
      "ddddd",
      "sss"
    ]
  }
}
```

Response example:

```json
{
  "id": 1,
  "uid": 1,
  "name": "filters-template-modificato",
  "xml": {
    "preserve_whitespace": true,
    "translate_elements": [
      "ciao",
      "casa2"
    ]
  },
  "yaml": {
    "translate_keys": [
      "saas",
      "ddddd",
      "sss"
    ]
  },
  "json": {
    "extract_arrays": true,
    "translate_keys": [
      "pappa"
    ]
  },
  "ms_word": [],
  "ms_excel": [],
  "ms_powerpoint": [],
  "createdAt": "2024-08-12T17:42:15+02:00",
  "modifiedAt": "2024-08-12T15:42:15+02:00"
}
```

### Delete

Use this endpoint to delete an existing template:

```
DELETE /api/v3/filters-config-template/[:id]
```

If no errors are thrown the endpoint will display the id of the new template:

```json
{
    "id": 1
}
```

### Fetch all

Use this endpoint to fetch all templates:

```
GET /api/v3/filters-config-template/
```

```json
{
  "current_page": 1,
  "per_page": 20,
  "last_page": 1,
  "total": 2,
  "prev": null,
  "next": null,
  "items": [
    {
      "id": 1,
      "uid": 1,
      "name": "filters-template-2",
      "xml": {
        "translate_elements": [
          "ciao",
          "casa"
        ]
      },
      "yaml": [],
      "json": [],
      "ms_word": [],
      "ms_excel": [],
      "ms_powerpoint": [],
      "createdAt": "2024-08-12T17:42:15+02:00",
      "modifiedAt": "2024-08-12T15:42:15+02:00"
    }
  ]
}
```

### Fetch single template

Use this endpoint to fetch a saved template:

```
GET /api/v3/filters-config-template/[:id]
```

If the id does not belong to an existing model a 404 code will be return.

Response example:

```json
{
  "id": 1,
  "uid": 1,
  "name": "filters-template-modificato",
  "xml": {
    "preserve_whitespace": true,
    "translate_elements": [
      "ciao",
      "casa2"
    ]
  },
  "yaml": {
    "translate_keys": [
      "saas",
      "ddddd",
      "sss"
    ]
  },
  "json": {
    "extract_arrays": true,
    "translate_keys": [
      "pappa"
    ]
  },
  "ms_word": [],
  "ms_excel": [],
  "ms_powerpoint": [],
  "createdAt": "2024-08-12T17:42:15+02:00",
  "modifiedAt": "2024-08-12T18:02:44+02:00"
}
```


### Get values for the default template

Use this endpoint to fetch the default template:

```
GET /api/v3/filters-config-template/default
```

```json
{
  "id": 0,
  "uid": 1,
  "name": "default",
  "xml": {
    "preserve_whitespace": false,
    "translate_elements": [],
    "do_not_translate_elements": [],
    "translate_attributes": []
  },
  "yaml": {
    "extract_arrays": false,
    "translate_keys": [],
    "do_not_translate_keys": []
  },
  "json": {
    "extract_arrays": false,
    "escape_forward_slashes": false,
    "translate_keys": [],
    "do_not_translate_keys": [],
    "context_keys": [],
    "character_limit": []
  },
  "ms_word": {
    "extract_doc_properties": false,
    "extract_comments": false,
    "extract_headers_footers": false,
    "extract_hidden_text": false,
    "accept_revisions": false,
    "exclude_styles": [],
    "exclude_highlight_colors": []
  },
  "ms_excel": {
    "extract_doc_properties": false,
    "extract_hidden_cells": false,
    "extract_diagrams": false,
    "extract_drawings": false,
    "extract_sheet_names": false,
    "exclude_columns": []
  },
  "ms_powerpoint": {
    "extract_doc_properties": false,
    "extract_hidden_slides": false,
    "extract_notes": true,
    "translate_slides": []
  },
  "created_at": "2024-08-12 19:41:16",
  "modified_at": "2024-08-12 19:41:16"
}
```