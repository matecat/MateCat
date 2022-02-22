**QAModel template API**

In order to create a fully customizable (and reusable) QA Model template some new domain entities were introduced: 

- `QAModelTemplateStruct`
- `QAModelTemplateCategoryStruct`
- `QAModelTemplatePassfailStruct`
- `QAModelTemplatePassfailThresholdStruct`
- `QAModelTemplateSeverityStruct`

`QAModelTemplateStruct` is always the aggregate root.

To manage those templates a new API working with JSON schemas (see below) is avaliable.

**Validate a JSON**

In order to make easier working with JSON schemas a new component called `JSONValidator` was developed. It performs an agnostic validation against a provided JSON schema.

Example:

```php

$jsonSchema = file_get_contents( '/path/to/your/schema.json');
$jsonToBeValidated = file_get_contents('path/to/your/json.json');

$validator = new \Validator\JSONValidator($jsonSchema);

$validator->isValid($jsonToBeValidated); // return boolean
$validator->validate($invalidFile);      // return an array of errors

```

This validator was built on top of [Swagger PHP](https://github.com/nabbar/SwaggerValidator-PHP).

**QA Model JSON schema**

This endpoint returns the JSON schema used to validate QAModels:

```
GET /api/v3/qa_model_template/schema
```

As you may notice the schema uses the Swagger naming convenctions.

**Validate a JSON before submit**

To create or update a new QA Model Template you are required to submit the complete JSON model to the APIs.

You can use this endpoint to validate your JSON:

```
POST /api/v3/qa_model_template/validate
```

Provide in the request body the JSON to be validated.

The endpoint will return an array of errors. For example if no errors are present the response will be:

```json
{
    "errors": []
}
```

**CRUD Operations: create**

Use this endpoint to create a new template:

```
POST /api/v3/qa_model_template
```

Provide in the request body the JSON model.

If no errors are thrown the endpoint will display the id of the new template:

```json
{
	"id": 4
}
```

**CRUD Operations: edit**

Use this endpoint to update an existing template:

```
PUT /api/v3/qa_model_template/[:id]
```

Provide in the request body the JSON model.

If no errors are thrown the endpoint will display the id of the new template:

```json
{
	"id": 4
}
```

**CRUD Operations: delete**

Use this endpoint to create a new template:

```
DELETE /api/v3/qa_model_template/[:id]
```

If no errors are thrown the endpoint will display the id of the deleted template:

```json
{
	"id": 4
}
```

**CRUD Operations: fetch single**

Use this endpoint to fetch a saved template:

```
GET /api/v3/qa_model_template/[:id]
```

If the id does not belong to existing model a 404 code will be return

Response example:

```json
{
    "id": "2",
    "uid": "1",
    "label": "Uberr - Marketing",
    "version": "1",
    "categories": [
        {
            "id": "8",
            "id_template": "2",
            "id_parent": null,
            "label": "Readability",
            "code": "RDB",
            "dqf_id": null,
            "sort": null,
            "severities": [
                {
                    "id": "22",
                    "id_category": "8",
                    "label": "Minor error",
                    "penalty": 2,
                    "sort": null
                },
                {
                    "id": "23",
                    "id_category": "8",
                    "label": "Major error",
                    "penalty": 2,
                    "sort": null
                },
                {
                    "id": "24",
                    "id_category": "8",
                    "label": "Critical Error",
                    "penalty": 4,
                    "sort": null
                }
            ]
        },
        {
            "id": "9",
            "id_template": "2",
            "id_parent": null,
            "label": "Compliance",
            "code": "COM",
            "dqf_id": null,
            "sort": null,
            "severities": [
                {
                    "id": "25",
                    "id_category": "9",
                    "label": "Minor error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "26",
                    "id_category": "9",
                    "label": "Major error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "27",
                    "id_category": "9",
                    "label": "Critical Error",
                    "penalty": 3,
                    "sort": null
                }
            ]
        },
        {
            "id": "10",
            "id_template": "2",
            "id_parent": null,
            "label": "Grammar",
            "code": "GRA",
            "dqf_id": null,
            "sort": null,
            "severities": [
                {
                    "id": "28",
                    "id_category": "10",
                    "label": "Minor error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "29",
                    "id_category": "10",
                    "label": "Major error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "30",
                    "id_category": "10",
                    "label": "Critical Error",
                    "penalty": 3,
                    "sort": null
                }
            ]
        },
        {
            "id": "11",
            "id_template": "2",
            "id_parent": null,
            "label": "Punctuation/Spelling/Typo",
            "code": "PST",
            "dqf_id": null,
            "sort": null,
            "severities": [
                {
                    "id": "31",
                    "id_category": "11",
                    "label": "Minor error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "32",
                    "id_category": "11",
                    "label": "Major error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "33",
                    "id_category": "11",
                    "label": "Critical Error",
                    "penalty": 3,
                    "sort": null
                }
            ]
        },
        {
            "id": "12",
            "id_template": "2",
            "id_parent": null,
            "label": "Meaning",
            "code": "MNG",
            "dqf_id": null,
            "sort": null,
            "severities": [
                {
                    "id": "34",
                    "id_category": "12",
                    "label": "Minor error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "35",
                    "id_category": "12",
                    "label": "Major error",
                    "penalty": 2,
                    "sort": null
                },
                {
                    "id": "36",
                    "id_category": "12",
                    "label": "Critical Error",
                    "penalty": 4,
                    "sort": null
                }
            ]
        },
        {
            "id": "13",
            "id_template": "2",
            "id_parent": null,
            "label": "Style",
            "code": "STY",
            "dqf_id": null,
            "sort": null,
            "severities": [
                {
                    "id": "37",
                    "id_category": "13",
                    "label": "Minor error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "38",
                    "id_category": "13",
                    "label": "Major error",
                    "penalty": 2,
                    "sort": null
                },
                {
                    "id": "39",
                    "id_category": "13",
                    "label": "Critical Error",
                    "penalty": 4,
                    "sort": null
                }
            ]
        },
        {
            "id": "14",
            "id_template": "2",
            "id_parent": null,
            "label": "Terminology",
            "code": "TER",
            "dqf_id": null,
            "sort": null,
            "severities": [
                {
                    "id": "40",
                    "id_category": "14",
                    "label": "Minor error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "41",
                    "id_category": "14",
                    "label": "Major error",
                    "penalty": 1,
                    "sort": null
                },
                {
                    "id": "42",
                    "id_category": "14",
                    "label": "Critical Error",
                    "penalty": 3,
                    "sort": null
                }
            ]
        }
    ],
    "passfail": {
        "id": "2",
        "id_template": "2",
        "type": "points_per_thousand",
        "thresholds": [
            {
                "id": "3",
                "id_passfail": "2",
                "label": "T",
                "value": "10"
            },
            {
                "id": "4",
                "id_passfail": "2",
                "label": "R1",
                "value": "3"
            }
        ]
    }
}
```

**CRUD Operations: fetch all**

Use this endpoint to fetch all templates:

```
GET /api/v3/qa_model_template
```

The API will return a paginated list of items (20 per page). Response example:

```json
{
    "current_page": 1,
    "per_page": 20,
    "last_page": 1,
    "prev": null,
    "next": null,
    "items": [
        ...
    ]
}
```

**A real example**

Take a look at this real CURL example:

```
curl --location --request POST 'https://dev.matecat.com/api/v3/qa_model_template' \
--header 'x-matecat-key: 5ebv4p5zriji9gadzff1' \
--header 'x-matecat-secret: v8flfff1mtbs7nnbcsb4' \
--header 'Content-Type: application/json' \
--data-raw '{
  "model": {
    "version" : 1,
    "label": "Uberr - Marketing",
    "categories": [
      {
        "label": "Readability",
        "code": "RDB",
        "severities": [
          {
            "label": "Minor error",
            "code": "Min",
            "dqf_id" : 2,
            "penalty": 2
          },
          {
            "label": "Major error",
            "code": "Maj",
            "dqf_id" : 2,
            "penalty": 2
          },
          {
            "label": "Critical Error",
            "code": "Cri",
            "dqf_id" : 3,
            "penalty": 4
          }
        ]
      },
      {
        "label": "Compliance",
        "code": "COM",
        "severities": [
          {
            "label": "Minor error",
            "code": "Min",
            "dqf_id" : 1,
            "penalty": 0.5
          },
          {
            "label": "Major error",
            "code": "Maj",
            "dqf_id" : 2,
            "penalty": 1
          },
          {
            "label": "Critical Error",
            "code": "Cri",
            "dqf_id" : 3,
            "penalty": 3
          }
        ]
      },
      {
        "label": "Grammar",
        "code": "GRA",
        "severities": [
          {
            "label": "Minor error",
            "code": "Min",
            "dqf_id" : 1,
            "penalty": 0.5
          },
          {
            "label": "Major error",
            "code": "Maj",
            "dqf_id" : 2,
            "penalty": 1
          },
          {
            "label": "Critical Error",
            "code": "Cri",
            "dqf_id" : 3,
            "penalty": 3
          }
        ]
      },
      {
        "label": "Punctuation/Spelling/Typo",
        "code": "PST",
        "severities": [
          {
            "label": "Minor error",
            "code": "Min",
            "dqf_id" : 1,
            "penalty": 0.5
          },
          {
            "label": "Major error",
            "code": "Maj",
            "dqf_id" : 2,
            "penalty": 1
          },
          {
            "label": "Critical Error",
            "code": "Cri",
            "dqf_id" : 3,
            "penalty": 3
          }
        ]
      },
      {
        "label": "Meaning",
        "code": "MNG",
        "severities": [
          {
            "label": "Minor error",
            "code": "Min",
            "dqf_id" : 1,
            "penalty": 1
          },
          {
            "label": "Major error",
            "code": "Maj",
            "dqf_id" : 2,
            "penalty": 2
          },
          {
            "label": "Critical Error",
            "code": "Cri",
            "dqf_id" : 3,
            "penalty": 4
          }
        ]
      },
      {
        "label": "Style",
        "code": "STY",
        "severities": [
          {
            "label": "Minor error",
            "code": "Min",
            "dqf_id" : 1,
            "penalty": 1
          },
          {
            "label": "Major error",
            "code": "Maj",
            "dqf_id" : 2,
            "penalty": 2
          },
          {
            "label": "Critical Error",
            "code": "Cri",
            "dqf_id" : 3,
            "penalty": 4
          }
        ]
      },
      {
        "label": "Terminology",
        "code": "TER",
        "severities": [
          {
            "label": "Minor error",
            "code": "Min",
            "dqf_id" : 1,
            "penalty": 0.5
          },
          {
            "label": "Major error",
            "code": "Maj",
            "dqf_id" : 2,
            "penalty": 1
          },
          {
            "label": "Critical Error",
            "code": "Cri",
            "dqf_id" : 3,
            "penalty": 3
          }
        ]
      }
    ],
    "passfail" : {
      "type" : "points_per_thousand",
      "thresholds" : [
        {
          "label": "T",
          "value": 10
        },
        {
          "label": "R1",
          "value": 3
        }
      ]
    }
  }
}
'
```