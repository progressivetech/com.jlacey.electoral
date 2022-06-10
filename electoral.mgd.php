<?php

return [
    [
    'name' => 'ContactType_Official',
    'entity' => 'ContactType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => [ 'name' ],
      'values' => [
        'name' => 'Official',
        'label' => 'Official',
        'description' => NULL,
        'image_URL' => NULL,
        'parent_id.name' => 'Individual',
        'is_active' => TRUE,
        'is_reserved' => FALSE,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_official_info',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'name' => 'official_info',
        'title' => 'Official Info',
        'extends' => 'Individual',
        'extends_entity_column_value' => [
          'Official',
        ],
        'style' => 'Tab',
        'collapse_display' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'weight' => 43,
        'is_active' => TRUE,
        'is_multiple' => FALSE,
        'min_multiple' => NULL,
        'max_multiple' => NULL,
        'collapse_adv_display' => FALSE,
        'created_date' => NULL,
        'is_reserved' => TRUE,
        'is_public' => TRUE,
        'icon' => NULL,
        'extends_entity_column_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_official_info_CustomField_electoral_office',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'official_info',
        'name' => 'electoral_office',
        'label' => 'Office',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 255,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_official_info_CustomField_electoral_party',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'official_info',
        'name' => 'electoral_party',
        'label' => 'Party',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 255,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_official_info_CustomField_electoral_ocd_id_official',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'official_info',
        'name' => 'electoral_ocd_id_official',
        'label' => 'Open Civic Data ID',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => 'This is a unique identifier for the district represented.',
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => TRUE,
        'options_per_line' => NULL,
        'text_length' => 255,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => FALSE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_official_info_CustomField_electoral_current_term_start_date',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'official_info',
        'name' => 'electoral_current_term_start_date',
        'label' => 'Current Term Start Date',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => 'mm/dd/yy',
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => FALSE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_official_info_CustomField_electoral_term_end_date',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'official_info',
        'name' => 'electoral_term_end_date',
        'label' => 'Term End Date',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => 'mm/dd/yy',
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => FALSE,
        'option_group_id' => NULL,
      ],
    ],
  ],
];
  [
    'name' => 'electoral_all_districts',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name'          => 'Electoral API - Districts Lookup',
      'description'   => 'Adds district information via the Electoral API',
      'run_frequency' => 'Daily',
      'api_entity'    => 'Electoral',
      'api_action'    => 'districts',
      'is_active'     => 0,
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'name' => 'electoral_districts_level_options',
        'title' => 'Level',
        'description' => NULL,
        'data_type' => NULL,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'is_locked' => FALSE,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_Country',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'Country',
        'value' => 'country',
        'name' => 'Country',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_State/Province',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'State/Province',
        'value' => 'administrativeArea1',
        'name' => 'State/Province',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_County',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'County',
        'value' => 'administrativeArea2',
        'name' => 'County',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_Voting',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'Voting',
        'value' => 'voting',
        'name' => 'Voting',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_Legislative',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'Legislative',
        'value' => 'legislative',
        'name' => 'Legislative',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_City',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'City',
        'value' => 'locality',
        'name' => 'City',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_Judicial',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'Judicial',
        'value' => 'judicial',
        'name' => 'Judicial',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_Police',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'Police',
        'value' => 'police',
        'name' => 'Police',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_level_options_OptionValue_School',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_level_options',
        'label' => 'School',
        'value' => 'school',
        'name' => 'School',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_chamber_options',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'name' => 'electoral_districts_chamber_options',
        'title' => 'Chamber',
        'description' => NULL,
        'data_type' => NULL,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'is_locked' => FALSE,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_chamber_options_OptionValue_Upper',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_chamber_options',
        'label' => 'Upper',
        'value' => 'upper',
        'name' => 'Upper',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_districts_chamber_options_OptionValue_Lower',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name', 'option_group_id'],
      'values' => [
        'option_group_id.name' => 'electoral_districts_chamber_options',
        'label' => 'Lower',
        'value' => 'lower',
        'name' => 'Lower',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => TRUE,
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'name' => 'electoral_districts',
        'title' => 'Electoral Districts',
        'extends' => 'Contact',
        'extends_entity_column_value' => NULL,
        'style' => 'Tab with table',
        'collapse_display' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'weight' => 1,
        'is_active' => TRUE,
        'is_multiple' => TRUE,
        'min_multiple' => NULL,
        'max_multiple' => NULL,
        'collapse_adv_display' => FALSE,
        'created_date' => NULL,
        'is_reserved' => TRUE,
        'is_public' => TRUE,
        'icon' => NULL,
        'extends_entity_column_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_level',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_level',
        'label' => 'Level',
        'data_type' => 'String',
        'html_type' => 'Select',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 128,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'option_group_id.name' => 'electoral_districts_level_options',
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_states_provinces',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_states_provinces',
        'label' => 'States/Provinces',
        'data_type' => 'StateProvince',
        'html_type' => 'Select',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_counties',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_counties',
        'label' => 'County',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 128,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_cities',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_cities',
        'label' => 'City',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 128,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_chamber',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_chamber',
        'label' => 'Chamber',
        'data_type' => 'String',
        'html_type' => 'Select',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 128,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'option_group_id.name' => 'electoral_districts_chamber_options',
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_district',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_district',
        'label' => 'District',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 128,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_note',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_note',
        'label' => 'Note',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 128,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_in_office',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_in_office',
        'label' => 'In Office?',
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_modified_date',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_modified_date',
        'label' => 'Last Updated',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => 'mm/dd/yy',
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_valid_from',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_valid_from',
        'label' => 'Valid From',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => 'mm/dd/yy',
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_valid_to',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_valid_to',
        'label' => 'Valid To',
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => 'mm/dd/yy',
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => TRUE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_electoral_districts_CustomField_electoral_ocd_id_district',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => ['name'],
      'values' => [
        'custom_group_id.name' => 'electoral_districts',
        'name' => 'electoral_ocd_id_district',
        'label' => 'Open Civic Data ID',
        'data_type' => 'String',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => TRUE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => NULL,
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => NULL,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => NULL,
        'note_rows' => NULL,
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => FALSE,
        'option_group_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_api_data_providers',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => [ 'name' ],
      'values' => [
        'name' => 'electoral_api_data_providers',
        'title' => 'Electoral API Data Providers',
        'description' => NULL,
        'data_type' => 'String',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
        'is_locked' => FALSE,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_api_data_providers_OptionValue_\Civi\Electoral\Api\Cicero',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => [ 'name' ],
      'values' => [
        'option_group_id.name' => 'electoral_api_data_providers',
        'label' => 'Cicero',
        'value' => '1',
        'name' => '\Civi\Electoral\Api\Cicero',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => FALSE,
        'is_reserved' => FALSE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_electoral_api_data_providers_OptionValue_\Civi\Electoral\Api\GoogleCivicInformation',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'match' => [ 'name' ],
      'values' => [
        'option_group_id.name' => 'electoral_api_data_providers',
        'label' => 'Google Civic',
        'value' => '2',
        'name' => '\Civi\Electoral\Api\GoogleCivicInformation',
        'grouping' => NULL,
        'filter' => 0,
        'is_default' => FALSE,
        'description' => NULL,
        'is_optgroup' => FALSE,
        'is_reserved' => FALSE,
        'is_active' => TRUE,
        'icon' => NULL,
        'color' => NULL,
        'component_id' => NULL,
        'domain_id' => NULL,
        'visibility_id' => NULL,
      ],
    ],
  ],
];
